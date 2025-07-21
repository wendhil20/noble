<?php
include '../../connection/connect.php';

// ✅ Define the tables before using in foreach
$tables = ['nobleaccount'];

foreach ($tables as $table) {
    // Kunin ang current max id
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];

    if ($max_id > 0) {
        // Check if the max_id row exists
        $result2 = $conn->query("SELECT COUNT(*) AS count FROM $table WHERE id = $max_id");
        $row2 = $result2->fetch_assoc();

        if ((int)$row2['count'] === 0) {
            // Reset AUTO_INCREMENT to max_id
            $conn->query("ALTER TABLE $table AUTO_INCREMENT = $max_id");
        }
    } else {
        // Walang laman ang table, reset to 1
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $lvl = trim($_POST['lvl']);

    // Validate inputs
    if (empty($fullname) || empty($email) || empty($password) || empty($lvl)) {
        echo "All fields are required!";
        exit;
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "Email is already registered!";
        $check->close();
        $conn->close();
        exit;
    }

    $check->close();

    // Secure password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert into nobleaccount with fullname
    $stmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $fullname, $email, $hashed_password, $lvl);

    if ($stmt->execute()) {
        echo "Account registered successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
