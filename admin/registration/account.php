<?php
include '../../connection/connect.php';

// Auto-increment reset function (moved to separate function for better organization)
function resetAutoIncrement($conn, $tables) {
    foreach ($tables as $table) {
        try {
            // Get current max id
            $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
            $row = $result->fetch_assoc();
            $max_id = (int)$row['max_id'];

            if ($max_id > 0) {
                // Reset AUTO_INCREMENT to max_id + 1
                $conn->query("ALTER TABLE $table AUTO_INCREMENT = " . ($max_id + 1));
            } else {
                // Empty table, reset to 1
                $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
            }
        } catch (Exception $e) {
            error_log("Error resetting auto increment for table $table: " . $e->getMessage());
        }
    }
}

// Initialize variables
$success = "";
$error = "";

// Reset auto increment for required tables
$tables = ['nobleaccount'];
resetAutoIncrement($conn, $tables);

// Check for success message from redirect and clean URL
if (isset($_GET['success']) && $_GET['success'] === 'true') {
    $success = "Registration successful!";
    // Clean the URL by redirecting without the success parameter
    echo "<script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    </script>";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize and validate input
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $lvl = trim($_POST['lvl'] ?? '');
    $supplier_id = isset($_POST['supplier_id']) && !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

    // Validation
    if (empty($fullname) || empty($email) || empty($password) || empty($lvl)) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Convert email to lowercase to ensure consistency
        $email = strtolower($email);
        
        // Initialize registration success flag
        $registrationSuccess = false;
        
        try {
            // Check if email already exists (case-insensitive)
            $checkEmail = $conn->prepare("SELECT id FROM nobleaccount WHERE LOWER(email) = LOWER(?)");
            $checkEmail->bind_param("s", $email);
            $checkEmail->execute();
            $result = $checkEmail->get_result();

            if ($result->num_rows > 0) {
                $error = "This email address is already registered. Please use a different email or try logging in.";
            } else {
                // Handle supplier registration
                if ($lvl === 'supplier') {
                    if (empty($supplier_id)) {
                        $error = "Please enter a supplier ID number.";
                    } else {
                        // Check if supplier_id exists and get supplier info
                        $checkSupplierExists = $conn->prepare("SELECT id, lvl FROM nobleaccount WHERE id = ?");
                        $checkSupplierExists->bind_param("i", $supplier_id);
                        $checkSupplierExists->execute();
                        $supplierResult = $checkSupplierExists->get_result();

                        if ($supplierResult->num_rows == 0) {
                            $error = "Supplier ID does not exist.";
                        } else {
                            $supplierData = $supplierResult->fetch_assoc();
                         
                            if (empty($error)) {
                                // Check if supplier_id is already linked to another account
                                $checkSupplierLinked = $conn->prepare("SELECT id, fullname FROM nobleaccount WHERE supplier_id = ?");
                                $checkSupplierLinked->bind_param("i", $supplier_id);
                                $checkSupplierLinked->execute();
                                $linkedResult = $checkSupplierLinked->get_result();

                                if ($linkedResult->num_rows > 0) {
                                    $linkedAccount = $linkedResult->fetch_assoc();
                                    $error = "This supplier ID (#$supplier_id) is already linked to another account. Each supplier ID can only be used once.";
                                } else {
                                    // Insert supplier account
                                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                    $insertStmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl, supplier_id) VALUES (?, ?, ?, ?, ?)");
                                    $insertStmt->bind_param("ssssi", $fullname, $email, $hashed_password, $lvl, $supplier_id);
                                    
                                    if ($insertStmt->execute()) {
                                        $registrationSuccess = true;
                                    } else {
                                        $error = "Registration failed. Please try again.";
                                    }
                                    $insertStmt->close();
                                }
                                $checkSupplierLinked->close();
                            }
                        }
                        $checkSupplierExists->close();
                    }
                } else {
                    // Handle non-supplier registration
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insertStmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl) VALUES (?, ?, ?, ?)");
                    $insertStmt->bind_param("ssss", $fullname, $email, $hashed_password, $lvl);
                    
                    if ($insertStmt->execute()) {
                        $registrationSuccess = true;
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                    $insertStmt->close();
                }
            }
            $checkEmail->close();
            
        } catch (Exception $e) {
            $error = "An error occurred during registration. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
        
        // Close connection
        if ($conn) {
            $conn->close();
        }
        
        // Redirect only if registration was successful
        if ($registrationSuccess && empty($error)) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=true");
            exit();
        }
    }
}

// Close connection if still open
if (isset($conn) && $conn) {
    $conn->close();
}
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
            <div id="error-notification" class="bg-red-100 border border-red-300 text-red-700 p-3 rounded mb-4 transition-opacity duration-500">
                <div class="flex justify-between items-start">
                    <div>
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <button onclick="hideNotification('error-notification')" class="text-red-500 hover:text-red-700 ml-2 text-lg font-bold leading-none">&times;</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div id="success-notification" class="bg-green-100 border border-green-300 text-green-700 p-3 rounded mb-4 transition-opacity duration-500">
                <div class="flex justify-between items-start">
                    <div>
                        <strong>Success:</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                    <button onclick="hideNotification('success-notification')" class="text-green-500 hover:text-green-700 ml-2 text-lg font-bold leading-none">&times;</button>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4" novalidate>
            <!-- Full Name -->
            <div>
                <label for="fullname" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input 
                    type="text" 
                    id="fullname"
                    name="fullname" 
                    required 
                    class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                    placeholder="Enter your full name"
                    value="<?php echo isset($_POST['fullname']) && !empty($success) ? '' : (isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''); ?>"
                >
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input 
                    type="email" 
                    id="email"
                    name="email" 
                    required 
                    class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                    placeholder="Enter your email (will be converted to lowercase)"
                    value="<?php echo isset($_POST['email']) && !empty($success) ? '' : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>"
                >
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input 
                    type="password" 
                    id="password"
                    name="password" 
                    required 
                    minlength="6"
                    class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                    placeholder="Enter password (min. 6 characters)"
                >
            </div>

            <!-- Account Level -->
            <div>
                <label for="lvl" class="block text-sm font-medium text-gray-700 mb-1">Account Level</label>
                <select 
                    name="lvl" 
                    id="lvl-select" 
                    required 
                    class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                    onchange="toggleSupplierDropdown(this.value)"
                >
                    <option value="">Select Account Level</option>
                    <option value="superadmin" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'superadmin' && empty($success)) ? 'selected' : ''; ?>>Superadmin</option>
                    <option value="sales" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'sales' && empty($success)) ? 'selected' : ''; ?>>Sales</option>
                    <option value="productspecialist" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'productspecialist' && empty($success)) ? 'selected' : ''; ?>>Product Specialist</option>
                    <option value="supplier" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'supplier' && empty($success)) ? 'selected' : ''; ?>>Supplier</option>
                    <option value="accountant" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'accountant' && empty($success)) ? 'selected' : ''; ?>>Accountant</option>
                    <option value="logistic" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'logistic' && empty($success)) ? 'selected' : ''; ?>>Logistic</option>
                </select>
            </div>

            <!-- Supplier ID input (only visible if 'supplier' selected) -->
            <div id="supplier-dropdown" style="display: <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'supplier' && empty($success)) ? 'block' : 'none'; ?>;">
                <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-1">Supplier ID</label>
                <input 
                    type="number" 
                    id="supplier_id"
                    name="supplier_id" 
                    min="1"
                    class="w-full border border-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                    placeholder="Enter unique Supplier ID Number"
                    value="<?php echo isset($_POST['supplier_id']) && !empty($success) ? '' : (isset($_POST['supplier_id']) ? htmlspecialchars($_POST['supplier_id']) : ''); ?>"
                >
            </div>

            <button 
                type="submit" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md w-full transition duration-200 font-medium"
            >
                Register
            </button>
        </form>

        <p class="text-sm text-center mt-6 text-gray-600">
            Already have an account?
            <a href="login.php" class="text-blue-600 hover:underline font-medium">Login here</a>
        </p>
    </div>

    <script>
        function toggleSupplierDropdown(role) {
            const dropdown = document.getElementById('supplier-dropdown');
            const supplierInput = document.getElementById('supplier_id');
            
            if (role === 'supplier') {
                dropdown.style.display = 'block';
                supplierInput.required = true;
            } else {
                dropdown.style.display = 'none';
                supplierInput.required = false;
                supplierInput.value = ''; // Clear input when hiding
            }
        }

        function hideNotification(notificationId) {
            const notification = document.getElementById(notificationId);
            if (notification) {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 500);
            }
        }

        // Auto-hide notifications after specified time
        function autoHideNotifications() {
            const errorNotif = document.getElementById('error-notification');
            const successNotif = document.getElementById('success-notification');
            
            if (errorNotif) {
                setTimeout(() => {
                    hideNotification('error-notification');
                }, 5000);
            }
            
            if (successNotif) {
                setTimeout(() => {
                    hideNotification('success-notification');
                }, 3000); // Success messages hide faster
            }
        }

        // Clean URL after showing success message
        function cleanUrl() {
            if (window.location.search.includes('success=')) {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState(null, null, url.toString());
            }
        }

        // Maintain supplier dropdown state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const levelSelect = document.getElementById('lvl-select');
            toggleSupplierDropdown(levelSelect.value);
            
            // Clean URL and start auto-hide timer for notifications
            cleanUrl();
            autoHideNotifications();
        });
    </script>
</body>
</html>