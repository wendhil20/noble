<?php
include '../../connection/connect.php';

$variants = mysqli_query($conn, "SELECT * FROM variants");

while ($variant = mysqli_fetch_assoc($variants)) {
    echo "<h3 style='color: orange'>" . htmlspecialchars($variant['name']) . "</h3>";
    $variantId = $variant['id'];
    $images = mysqli_query($conn, "SELECT * FROM images WHERE variant_id = $variantId");

    while ($img = mysqli_fetch_assoc($images)) {
        echo "<div style='display:inline-block;margin:10px;text-align:center'>";
        echo "<img src='data:image/jpeg;base64," . base64_encode($img['image_data']) . "' width='120' height='120'><br>";
        echo "<small>" . htmlspecialchars($img['description']) . "</small>";
        echo "</div>";
    }
}
?>
