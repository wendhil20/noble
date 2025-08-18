<?php
include '../../../connection/connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])) {
    $id = (int) $_POST['id'];

    $delete = mysqli_query($conn, "DELETE FROM images WHERE id = $id");

    if ($delete) {
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit;
    } else {
        echo "Failed to delete.";
    }
}
?>
