<?php
include '../../connection/connect.php'; // adjust path

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variantName = trim($_POST['variant_name']);
    if (!empty($variantName)) {
        // Check if variant already exists
        $check = mysqli_query($conn, "SELECT id FROM variants WHERE name = '$variantName'");
        if (mysqli_num_rows($check) > 0) {
            $variant = mysqli_fetch_assoc($check);
            $variantId = $variant['id'];
        } else {
            // Insert variant
            mysqli_query($conn, "INSERT INTO variants (name) VALUES ('$variantName')");
            $variantId = mysqli_insert_id($conn);
        }

        // Redirect to upload page
        header("Location: upload_images.php?variant_id=$variantId&variant_name=" . urlencode($variantName));
        exit();
    }
}
?>
