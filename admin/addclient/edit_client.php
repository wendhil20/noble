<?php
include '../../connection/connect.php'; // adjust path as needed

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $country = $_POST['country'];

    $stmt = $conn->prepare("UPDATE client_info SET name=?, address=?, email=?, contact=?, country=? WHERE id=?");
    $stmt->bind_param("sssssi", $name, $address, $email, $contact, $country, $id);
    $stmt->execute();

    echo "<script>alert('Client updated successfully'); window.parent.closeEditModal();</script>";
    exit;
}

// Load client data
$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM client_info WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();

if (!$client) {
    echo "Client not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Client</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
    <form method="POST" class="bg-white p-6 rounded-xl shadow-lg space-y-4">
        <input type="hidden" name="id" value="<?php echo $client['id']; ?>">

        <div>
            <label class="block text-sm font-medium">Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block text-sm font-medium">Address</label>
            <input type="text" name="address" value="<?php echo htmlspecialchars($client['address']); ?>" class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block text-sm font-medium">Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block text-sm font-medium">Contact</label>
            <input type="text" name="contact" value="<?php echo htmlspecialchars($client['contact']); ?>" class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block text-sm font-medium">Country</label>
            <input type="text" name="country" value="<?php echo htmlspecialchars($client['country']); ?>" class="w-full border rounded px-3 py-2" required>
        </div>

        <div class="text-right">
            <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">
                Save Changes
            </button>
        </div>
    </form>
</body>
</html>
