<?php
include '../../connection/connect.php';
session_start();

if (isset($_POST['submit'])) {
    $variantId = $_POST['variant_id'];
    $descriptions = $_POST['descriptions'];
    $categories = $_POST['categories'];
    $images = $_FILES['images'];

    for ($i = 0; $i < count($images['name']); $i++) {
        $imageTmp = $images['tmp_name'][$i];
        $imageData = addslashes(file_get_contents($imageTmp));
        $description = mysqli_real_escape_string($conn, $descriptions[$i]);
        $category = (int)$categories[$i];

        $query = "INSERT INTO images (variant_id, image_data, description, category)
                  VALUES ('$variantId', '$imageData', '$description', '$category')";

        if (!mysqli_query($conn, $query)) {
            echo "Error uploading image: " . mysqli_error($conn);
        }
    }

    echo "<script>alert('Images uploaded successfully!'); window.location.href='variant_select.php';</script>";
}
?>
