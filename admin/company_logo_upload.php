<?php
// company_logo_upload.php
session_start();
include '../connection/connect.php'; // <-- palitan mo ng tamang db connection mo

$message = "";

// ========== HANDLE FORM SUBMIT ==========
if (isset($_POST['upload'])) {

    if (!empty($_FILES['logo']['tmp_name'])) {

        $fileData = file_get_contents($_FILES['logo']['tmp_name']);

        // Prepare SQL
        $stmt = $conn->prepare("INSERT INTO company_logos (logo_blob, created_at) VALUES (?, NOW())");
        $stmt->bind_param("b", $fileData);

        // Send BLOB properly
        $stmt->send_long_data(0, $fileData);

        if ($stmt->execute()) {
            $message = "<p style='color: green;'>Image uploaded successfully!</p>";
        } else {
            $message = "<p style='color: red;'>Upload failed: " . $stmt->error . "</p>";
        }

        $stmt->close();
    } else {
        $message = "<p style='color: red;'>Please choose an image.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Company Logo</title>
</head>
<body>

<h2>Upload Company Logo</h2>

<?= $message ?>

<form action="" method="POST" enctype="multipart/form-data">
    <label>Select Logo:</label><br>
    <input type="file" name="logo" accept="image/*" required><br><br>

    <button type="submit" name="upload">Upload</button>
</form>

</body>
</html>
