<?php
session_start();
include '../../connection/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../cart_view.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $quantities = $_POST['quantities'] ?? [];

    // ✅ Loop through each submitted quantity
    foreach ($quantities as $item_id => $qty) {
        $qty = max(1, (int)$qty);

        // ✅ Update quantity in DB
        $stmt = $conn->prepare("UPDATE user_cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $qty, $item_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: ../cart_view.php");
exit;
