<?php
include '../../connection/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_id = (int)$_POST['id'];
    $descriptionpic = $_POST['descriptionpic'];
    
    // Start building the SQL update query
    $sql = "UPDATE product_variants SET descriptionpic = ?";
    $types = "s";
    $params = [$descriptionpic];
    
    // Handle image uploads
    $image_fields = ['imagedescription', 'imagedescriptiontwo', 'imagedescriptiontree', 'imagedescriptionfour'];
    
    foreach ($image_fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            // Read the uploaded file
            $image_data = file_get_contents($_FILES[$field]['tmp_name']);
            
            // Add to SQL query
            $sql .= ", $field = ?";
            $types .= "s";
            $params[] = $image_data;
        }
    }
    
    // Add WHERE clause
    $sql .= " WHERE id = ?";
    $types .= "i";
    $params[] = $variant_id;
    
    // Prepare and execute the statement
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Success - redirect back to the edit form
        header("Location: variant_edit.php?id=$variant_id&success=1");
        exit;
    } else {
        // Error
        header("Location: variant_edit.php?id=$variant_id&error=1");
        exit;
    }
} else {
    // If not POST request, redirect
    header("Location: variant_edit.php");
    exit;
}
?>