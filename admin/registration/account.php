<?php
include '../../connection/connect.php';

// Auto-increment reset function
function resetAutoIncrement($conn, $tables)
{
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
    <title>Account Registration - Noble Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        

        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .input-focus {
            transition: all 0.3s ease;
        }
        
        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }
        
        .btn-primary {
           
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .notification {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-section {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <!-- Header Section -->
        <div class="text-center mb-8 form-section">
            <div class="glass-effect rounded-2xl p-6 mb-6">
                <div class="w-16 h-16 bg-orange-400 rounded-full mx-auto mb-4 flex items-center justify-center">
                    <i class="fas fa-user-plus text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Account Registration</h1>
                <p class="text-gray-600 text-sm">NobleHomedepot Management System</p>
            </div>
        </div>

        <!-- Notification Messages -->
        <?php if (!empty($error)): ?>
            <div id="error-notification" class="notification glass-effect rounded-xl p-4 mb-6 border-l-4 border-red-500">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <h4 class="text-red-800 font-semibold text-sm">Registration Error</h4>
                        <p class="text-red-700 text-sm mt-1"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                    <button onclick="hideNotification('error-notification')" class="ml-4 text-red-500 hover:text-red-700 text-xl">&times;</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div id="success-notification" class="notification glass-effect rounded-xl p-4 mb-6 border-l-4 border-green-500">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500 text-lg"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <h4 class="text-green-800 font-semibold text-sm">Registration Successful</h4>
                        <p class="text-green-700 text-sm mt-1"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                    <button onclick="hideNotification('success-notification')" class="ml-4 text-green-500 hover:text-green-700 text-xl">&times;</button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <div class="glass-effect rounded-2xl p-8 form-section">
            <form method="POST" class="space-y-6" novalidate>
                <!-- Full Name -->
                <div class="space-y-2">
                    <label for="fullname" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user text-gray-400 mr-2"></i>Full Name
                    </label>
                    <input type="text" id="fullname" name="fullname" required 
                           class="w-full border border-gray-300 px-4 py-3 rounded-xl input-focus focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter your full name"
                           value="<?php echo isset($_POST['fullname']) && !empty($success) ? '' : (isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''); ?>">
                </div>

                <!-- Email -->
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-envelope text-gray-400 mr-2"></i>Email Address
                    </label>
                    <input type="email" id="email" name="email" required 
                           class="w-full border border-gray-300 px-4 py-3 rounded-xl input-focus focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter your email address"
                           value="<?php echo isset($_POST['email']) && !empty($success) ? '' : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">
                </div>

                <!-- Password -->
                <div class="space-y-2">
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-gray-400 mr-2"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required minlength="6" 
                               class="w-full border border-gray-300 px-4 py-3 pr-12 rounded-xl input-focus focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Minimum 6 characters">
                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                            <i id="password-icon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Account Level -->
                <div class="space-y-2">
                    <label for="lvl" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user-tag text-gray-400 mr-2"></i>Account Level
                    </label>
                    <select name="lvl" id="lvl-select" required 
                            class="w-full border border-gray-300 px-4 py-3 rounded-xl input-focus focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                            onchange="toggleDropdowns(this.value)">
                        <option value="">Select your role</option>
                        <option value="superadmin" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'superadmin') ? 'selected' : ''; ?>>Super Administrator</option>
                        <option value="sales" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'sales') ? 'selected' : ''; ?>>Sales Representative</option>
                        <option value="productspecialist" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'productspecialist') ? 'selected' : ''; ?>>Product Specialist</option>
                        <option value="accountant" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'accountant') ? 'selected' : ''; ?>>Accountant</option>
                        <option value="logistic" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'logistic') ? 'selected' : ''; ?>>Logistics Coordinator</option>
                        <option value="warehouse" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'warehouse') ? 'selected' : ''; ?>>Warehouse Manager</option>
                        <option value="hr" <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'hr') ? 'selected' : ''; ?>>Human Resources</option>
                    </select>
                </div>

                <!-- Sales ID -->
                <div id="sales-dropdown" class="space-y-2" style="display: <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'sales') ? 'block' : 'none'; ?>;">
                    <label for="sales_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-id-badge text-gray-400 mr-2"></i>Sales ID Number
                    </label>
                    <input type="number" id="sales_id" name="sales_id" min="1" 
                           class="w-full border border-gray-300 px-4 py-3 rounded-xl input-focus focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter your Sales ID"
                           value="<?php echo isset($_POST['sales_id']) && !empty($success) ? '' : (isset($_POST['sales_id']) ? htmlspecialchars($_POST['sales_id']) : ''); ?>">
                </div>

                <!-- Supplier ID -->
                <div id="supplier-dropdown" class="space-y-2" style="display: <?php echo (isset($_POST['lvl']) && $_POST['lvl'] === 'supplier') ? 'block' : 'none'; ?>;">
                    <label for="supplier_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-building text-gray-400 mr-2"></i>Supplier ID Number
                    </label>
                    <input type="number" id="supplier_id" name="supplier_id" min="1" 
                           class="w-full border border-gray-300 px-4 py-3 rounded-xl input-focus focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter your Supplier ID"
                           value="<?php echo isset($_POST['supplier_id']) && !empty($success) ? '' : (isset($_POST['supplier_id']) ? htmlspecialchars($_POST['supplier_id']) : ''); ?>">
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full  text-black font-semibold py-3 px-6 rounded-xl text-lg bg-orange-400">
                    <i class="fas fa-user-plus mr-2"></i>Create Account
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-6 pt-6 border-t border-gray-200 text-center">
                <p class="text-gray-500 text-sm">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Your information is secure and protected
                </p>
            </div>
        </div>
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

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        function hideNotification(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.animation = 'slideOut 0.3s ease-in forwards';
                setTimeout(() => el.style.display = 'none', 300);
            }
        }

        // Auto-hide notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', () => {
            toggleDropdowns(document.getElementById('lvl-select').value);
            
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    if (notification.style.display !== 'none') {
                        hideNotification(notification.id);
                    }
                }, 5000);
            });
        });

        // Add slideOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(-20px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>