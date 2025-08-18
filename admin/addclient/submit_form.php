<?php
include '../../connection/connect.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $address = $conn->real_escape_string($_POST['address']);
    $email = $conn->real_escape_string($_POST['email']);
    $contact = $conn->real_escape_string($_POST['contact']);
    $country = $conn->real_escape_string($_POST['country']);
    $client_type = $conn->real_escape_string($_POST['client_type']);

    // Check if email already exists
    $checkEmail = "SELECT id FROM client_info WHERE email = '$email'";
    $result = $conn->query($checkEmail);

    if ($result->num_rows > 0) {
        echo "Email already exists!";
    } else {
        // Check if table is empty
        $checkEmpty = "SELECT COUNT(*) AS total FROM client_info";
        $emptyResult = $conn->query($checkEmpty);
        $row = $emptyResult->fetch_assoc();

        if ($row['total'] == 0) {
            $conn->query("ALTER TABLE client_info AUTO_INCREMENT = 1");
        }

        // Insert data
        $sql = "INSERT INTO client_info (name, address, email, contact, country, client_type)
                VALUES ('$name', '$address', '$email', '$contact', '$country', '$client_type')";

        if ($conn->query($sql) === TRUE) {
            echo "Form submitted successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
    }
}

$conn->close();
?>
