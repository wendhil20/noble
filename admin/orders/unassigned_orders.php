<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
include '../navbar/top.php';

require_role(['sales', 'superadmin']);

// Redirect if not logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Set noble_name, noble_lvl, and noble_id if not yet set
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_id'] = 0;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

// Fetch unassigned orders
$sql = "SELECT id, customer_name, email, mobile, total, address FROM orders WHERE emp_id IS NULL OR emp_id = ''";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Unassigned Orders</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6 font-sans">
  <div class="container mx-auto bg-white p-6 rounded-lg shadow-md">
    <h1 class="text-2xl font-bold mb-4 text-orange-600">Unassigned Orders</h1>

    <?php if (isset($_GET['accepted']) && $_GET['accepted'] == "true"): ?>
      <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
        ✅ Order accepted successfully!
      </div>
    <?php endif; ?>

    <table class="min-w-full border text-sm text-left">
      <thead class="bg-orange-100 text-orange-800">
        <tr>
          <th class="py-2 px-4 border">Customer Name</th>
          <th class="py-2 px-4 border">Email</th>
          <th class="py-2 px-4 border">Mobile</th>
          <th class="py-2 px-4 border">Total</th>
          <th class="py-2 px-4 border">Address</th>
          <th class="py-2 px-4 border">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="py-2 px-4 border"><?php echo htmlspecialchars($row['customer_name']); ?></td>
              <td class="py-2 px-4 border"><?php echo htmlspecialchars($row['email']); ?></td>
              <td class="py-2 px-4 border"><?php echo htmlspecialchars($row['mobile']); ?></td>
              <td class="py-2 px-4 border">₱<?php echo number_format($row['total'], 2); ?></td>
              <td class="py-2 px-4 border"><?php echo htmlspecialchars($row['address']); ?></td>
              <td class="py-2 px-4 border">
                <form method="POST" action="accept_order.php">
                  <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                  <button type="submit" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                    Accept
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="text-center text-gray-500 py-4">No unassigned orders found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
