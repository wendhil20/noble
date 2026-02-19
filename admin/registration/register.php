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

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $lvl = trim($_POST['lvl']);
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

    // Validate inputs
    if (empty($fullname) || empty($email) || empty($password) || empty($lvl)) {
        $error = "All fields are required!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Email is already registered!";
        } else {
            // For supplier level: ensure supplier_id is provided and valid
            if ($lvl === 'supplier') {
                if (empty($supplier_id)) {
                    $error = "Please enter a supplier ID number.";
                } else {
                    // Check if the supplier_id exists in the database
                    $checkSupplierExists = $conn->prepare("SELECT id FROM nobleaccount WHERE id = ?");
                    $checkSupplierExists->bind_param("i", $supplier_id);
                    $checkSupplierExists->execute();
                    $checkSupplierExists->store_result();

                    if ($checkSupplierExists->num_rows == 0) {
                        $error = "Supplier ID does not exist.";
                    } else {
                        // Check if supplier_id is already linked to another account
                        $checkSupplier = $conn->prepare("SELECT id FROM nobleaccount WHERE supplier_id = ?");
                        $checkSupplier->bind_param("i", $supplier_id);
                        $checkSupplier->execute();
                        $checkSupplier->store_result();

                        if ($checkSupplier->num_rows > 0) {
                            $error = "This supplier ID is already linked to another account.";
                        } else {
                            $stmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl, supplier_id) VALUES (?, ?, ?, ?, ?)");
                            $stmt->bind_param("ssssi", $fullname, $email, $hashed_password, $lvl, $supplier_id);
                            if ($stmt->execute()) {
                                $success = "Supplier registration successful!";
                                // Close connections before redirect
                                $stmt->close();
                                $checkSupplier->close();
                                $checkSupplierExists->close();
                                $check->close();
                                $conn->close();
                                
                                // Redirect with success message
                                header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
                                exit();
                            } else {
                                $error = "Error: " . $stmt->error;
                            }
                            $stmt->close();
                        }
                        $checkSupplier->close();
                    }
                    $checkSupplierExists->close();
                }
            } else {
                // For non-supplier levels (no supplier_id needed)
                $stmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $fullname, $email, $hashed_password, $lvl);
                if ($stmt->execute()) {
                    $success = "Account registered successfully!";
                    // Close connections before redirect
                    $stmt->close();
                    $check->close();
                    $conn->close();
                    
                    // Redirect with success message
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
                    exit();
                } else {
                    $error = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
        }

        $check->close();
    }
    $conn->close();
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>