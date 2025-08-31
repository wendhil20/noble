<?php
include '../../connection/connect.php';

// Auto-increment reset function
function resetAutoIncrement($conn, $tables) {
    foreach ($tables as $table) {
        try {
            $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
            $row = $result->fetch_assoc();
            $max_id = (int)$row['max_id'];

            if ($max_id > 0) {
                $conn->query("ALTER TABLE $table AUTO_INCREMENT = " . ($max_id + 1));
            } else {
                $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
            }
        } catch (Exception $e) {
            error_log("Error resetting auto increment for table $table: " . $e->getMessage());
        }
    }
}

// Initialize
$success = "";
$error = "";
$tables = ['nobleaccount'];
resetAutoIncrement($conn, $tables);

if (isset($_GET['success']) && $_GET['success'] === 'true') {
    $success = "Registration successful!";
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $lvl = trim($_POST['lvl'] ?? '');
    $supplier_id = isset($_POST['supplier_id']) && !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $sales_id = isset($_POST['sales_id']) && !empty($_POST['sales_id']) ? (int)$_POST['sales_id'] : null;

    if (empty($fullname) || empty($email) || empty($password) || empty($lvl)) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        $email = strtolower($email);
        $registrationSuccess = false;

        try {
            $checkEmail = $conn->prepare("SELECT id FROM nobleaccount WHERE LOWER(email) = LOWER(?)");
            $checkEmail->bind_param("s", $email);
            $checkEmail->execute();
            $result = $checkEmail->get_result();

            if ($result->num_rows > 0) {
                $error = "This email address is already registered.";
            } else {
                if ($lvl === 'supplier') {
                    if (empty($supplier_id)) {
                        $error = "Please enter a Supplier ID number.";
                    } else {
                        $checkSupplierLinked = $conn->prepare("SELECT id FROM nobleaccount WHERE supplier_id = ?");
                        $checkSupplierLinked->bind_param("i", $supplier_id);
                        $checkSupplierLinked->execute();
                        if ($checkSupplierLinked->get_result()->num_rows > 0) {
                            $error = "This Supplier ID (#$supplier_id) is already linked.";
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $insertStmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl, supplier_id) VALUES (?, ?, ?, ?, ?)");
                            $insertStmt->bind_param("ssssi", $fullname, $email, $hashed_password, $lvl, $supplier_id);
                            if ($insertStmt->execute()) $registrationSuccess = true;
                            else $error = "Registration failed.";
                            $insertStmt->close();
                        }
                        $checkSupplierLinked->close();
                    }
                } elseif ($lvl === 'sales') {
                    if (empty($sales_id)) {
                        $error = "Please enter a Sales ID number.";
                    } else {
                        $checkSalesLinked = $conn->prepare("SELECT id FROM nobleaccount WHERE sales_id = ?");
                        $checkSalesLinked->bind_param("i", $sales_id);
                        $checkSalesLinked->execute();
                        if ($checkSalesLinked->get_result()->num_rows > 0) {
                            $error = "This Sales ID (#$sales_id) is already linked.";
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $insertStmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl, sales_id) VALUES (?, ?, ?, ?, ?)");
                            $insertStmt->bind_param("ssssi", $fullname, $email, $hashed_password, $lvl, $sales_id);
                            if ($insertStmt->execute()) $registrationSuccess = true;
                            else $error = "Registration failed.";
                            $insertStmt->close();
                        }
                        $checkSalesLinked->close();
                    }
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insertStmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl) VALUES (?, ?, ?, ?)");
                    $insertStmt->bind_param("ssss", $fullname, $email, $hashed_password, $lvl);
                    if ($insertStmt->execute()) $registrationSuccess = true;
                    else $error = "Registration failed.";
                    $insertStmt->close();
                }
            }
            $checkEmail->close();
        } catch (Exception $e) {
            $error = "An error occurred during registration.";
            error_log("Registration error: " . $e->getMessage());
        }

        if ($registrationSuccess && empty($error)) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=true");
            exit();
        }
    }
}

if (isset($conn) && $conn) $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
<div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
    <h2 class="text-2xl font-bold mb-6 text-center text-blue-600">Register</h2>

    <?php if (!empty($error)): ?>
    <div id="error-notification" class="bg-red-100 border border-red-300 text-red-700 p-3 rounded mb-4">
        <div class="flex justify-between items-start">
            <div><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></div>
            <button onclick="hideNotification('error-notification')" class="text-red-500 hover:text-red-700 ml-2">&times;</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div id="success-notification" class="bg-green-100 border border-green-300 text-green-700 p-3 rounded mb-4">
        <div class="flex justify-between items-start">
            <div><strong>Success:</strong> <?php echo htmlspecialchars($success); ?></div>
            <button onclick="hideNotification('success-notification')" class="text-green-500 hover:text-green-700 ml-2">&times;</button>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4" novalidate>
        <div>
            <label for="fullname" class="block text-sm font-medium mb-1">Full Name</label>
            <input type="text" id="fullname" name="fullname" required class="w-full border px-4 py-2 rounded-md"
                value="<?php echo isset($_POST['fullname']) && !empty($success) ? '' : (isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''); ?>">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium mb-1">Email</label>
            <input type="email" id="email" name="email" required class="w-full border px-4 py-2 rounded-md"
                value="<?php echo isset($_POST['email']) && !empty($success) ? '' : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium mb-1">Password</label>
            <input type="password" id="password" name="password" required minlength="6" class="w-full border px-4 py-2 rounded-md">
        </div>
        <div>
            <label for="lvl" class="block text-sm font-medium mb-1">Account Level</label>
            <select name="lvl" id="lvl-select" required class="w-full border px-4 py-2 rounded-md" onchange="toggleDropdowns(this.value)">
                <option value="">Select Account Level</option>
                <option value="superadmin" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'superadmin') ? 'selected' : ''; ?>>Superadmin</option>
                <option value="sales" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'sales') ? 'selected' : ''; ?>>Sales</option>
                <option value="productspecialist" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'productspecialist') ? 'selected' : ''; ?>>Product Specialist</option>
                <option value="supplier" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'supplier') ? 'selected' : ''; ?>>Supplier</option>
                <option value="accountant" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'accountant') ? 'selected' : ''; ?>>Accountant</option>
                <option value="logistic" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'logistic') ? 'selected' : ''; ?>>Logistic</option>
                <option value="warehouse" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'warehouse') ? 'selected' : ''; ?>>Warehouse</option>
                <option value="hr" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'hr') ? 'selected' : ''; ?>>HR</option>
            </select>
        </div>
        <!-- Sales ID -->
        <div id="sales-dropdown" style="display: <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'sales') ? 'block' : 'none'; ?>;">
            <label for="sales_id" class="block text-sm font-medium mb-1">Sales ID</label>
            <input type="number" id="sales_id" name="sales_id" min="1" class="w-full border px-4 py-2 rounded-md"
                value="<?php echo isset($_POST['sales_id']) && !empty($success) ? '' : (isset($_POST['sales_id']) ? htmlspecialchars($_POST['sales_id']) : ''); ?>">
        </div>
        <!-- Supplier ID -->
        <div id="supplier-dropdown" style="display: <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'supplier') ? 'block' : 'none'; ?>;">
            <label for="supplier_id" class="block text-sm font-medium mb-1">Supplier ID</label>
            <input type="number" id="supplier_id" name="supplier_id" min="1" class="w-full border px-4 py-2 rounded-md"
                value="<?php echo isset($_POST['supplier_id']) && !empty($success) ? '' : (isset($_POST['supplier_id']) ? htmlspecialchars($_POST['supplier_id']) : ''); ?>">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md w-full">Register</button>
    </form>
</div>
<script>
function toggleDropdowns(role) {
    const supplierDropdown = document.getElementById('supplier-dropdown');
    const salesDropdown = document.getElementById('sales-dropdown');
    const supplierInput = document.getElementById('supplier_id');
    const salesInput = document.getElementById('sales_id');

    supplierDropdown.style.display = (role === 'supplier') ? 'block' : 'none';
    salesDropdown.style.display = (role === 'sales') ? 'block' : 'none';

    supplierInput.required = (role === 'supplier');
    salesInput.required = (role === 'sales');

    if (role !== 'supplier') supplierInput.value = '';
    if (role !== 'sales') salesInput.value = '';
}
function hideNotification(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}
document.addEventListener('DOMContentLoaded', () => {
    toggleDropdowns(document.getElementById('lvl-select').value);
});
</script>
</body>
</html>
