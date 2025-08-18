<?php
include '../../../connection/connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'], $_POST['description'])) {
    $id = (int) $_POST['id'];
    $desc = mysqli_real_escape_string($conn, $_POST['description']);

    $update = mysqli_query($conn, "UPDATE images SET description = '$desc' WHERE id = $id");

    if ($update) {
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit;
    } else {
        echo "Failed to update.";
    }
}
?>
