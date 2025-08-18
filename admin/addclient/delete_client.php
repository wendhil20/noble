<?php
include '../../connection/connect.php'; // adjust path if needed

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Optional: Check if the client exists first (optional safety)
    $check = $conn->prepare("SELECT * FROM client_info WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        echo "Client not found.";
        exit;
    }

    // Proceed to delete
    $stmt = $conn->prepare("DELETE FROM client_info WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: insertclient.php?deleted=1"); // Change this to your client list page
        exit;
    } else {
        echo "Error deleting client.";
    }
} else {
    echo "Invalid request.";
}
?>
