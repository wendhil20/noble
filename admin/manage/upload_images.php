<?php
session_start();
$variantId = $_GET['variant_id'] ?? null;
$variantName = $_GET['variant_name'] ?? null;

if (!$variantId || !$variantName) {
    header("Location: variant_select.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Upload Images for <?= htmlspecialchars($variantName) ?></title>
</head>
<body>

<h2>Step 2: Upload Images for Variant: <span style="color: orange;"><?= htmlspecialchars($variantName) ?></span></h2>

<form action="upload.php" method="POST" enctype="multipart/form-data">
  <input type="hidden" name="variant_id" value="<?= $variantId ?>">

  <div id="imageFields">
    <div class="image-set">
      <input type="file" name="images[]" accept="image/*" required>
      <input type="text" name="descriptions[]" placeholder="Image description">
      <input type="number" name="categories[]" placeholder="Enter category number" required>
    </div>
  </div>

  <br>
  <button type="button" onclick="addField()">Add Another</button>
  <br><br>
  <button type="submit" name="submit">Upload</button>
</form>

<script>
function addField() {
  const div = document.createElement('div');
  div.classList.add('image-set');
  div.innerHTML = `
    <br>
    <input type="file" name="images[]" accept="image/*" required>
    <input type="text" name="descriptions[]" placeholder="Image description">
    <input type="number" name="categories[]" placeholder="Enter category number" required>
  `;
  document.getElementById('imageFields').appendChild(div);
}
</script>

</body>
</html>
