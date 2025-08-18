<?php
include '../../../connection/connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $variant_id = (int) $_POST['variant_id'];
    $description = $conn->real_escape_string($_POST['description']);

    // 1. Kunin ang category ng variant (from `variants` table)
    $query = "SELECT category FROM images WHERE id = $variant_id";
    $result = mysqli_query($conn, $query);

    if (!$result || mysqli_num_rows($result) == 0) {
        die("Error: Variant not found.");
    }

    $variant = mysqli_fetch_assoc($result);
    $category = $variant['category']; // This is numeric (e.g., 1, 2, 3)

    // 2. Basahin ang uploaded image
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        die("Error: No image uploaded or upload error.");
    }

    $imageData = file_get_contents($_FILES['image']['tmp_name']);
    $imageData = $conn->real_escape_string($imageData);

    // 3. I-save sa `images` table
    $sql = "INSERT INTO images (variant_id, image_data, description, category)
            VALUES ('$variant_id', '$imageData', '$description', '$category')";

    if ($conn->query($sql) === TRUE) {
        header("Location: ../manage.php?variant_id=$variant_id");
        exit;
    } else {
        echo "Database error: " . $conn->error;
    }
} else {
    echo "Invalid request method.";
}
?>
