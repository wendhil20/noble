<?php
include '../connection/connect.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "No image ID provided.";
    exit;
}

$query = "SELECT * FROM images WHERE id = $id";
$result = mysqli_query($conn, $query);
$image = mysqli_fetch_assoc($result);

if (!$image) {
    echo "Image not found.";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Image Detail</title>
</head>
<body>
    <img src="data:image/jpeg;base64,<?= base64_encode($image['image_data']) ?>" 
         style="width:300px;height:auto;" />
    <p><strong>Description:</strong> <?= htmlspecialchars($image['description']) ?></p>
    <p><strong>Category:</strong> <?= $image['category'] ?></p>
    
</body>
</html>
