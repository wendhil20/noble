<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

function resetAutoIncrement($conn, $tables)
{
    foreach ($tables as $table) {
        try {
            $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
            $row = $result->fetch_assoc();
            $max_id = (int) $row['max_id'];
            $conn->query("ALTER TABLE $table AUTO_INCREMENT = " . ($max_id > 0 ? $max_id + 1 : 1));
        } catch (Exception $e) {
            error_log("Error resetting auto increment for table $table: " . $e->getMessage());
        }
    }
}

$success = "";
$error = "";
$tables = ['nobleaccount'];
resetAutoIncrement($conn, $tables);

if (isset($_GET['success']) && $_GET['success'] === 'true') {
    $success = "Account created successfully!";
    echo "<script>if(window.history.replaceState){window.history.replaceState(null,null,window.location.pathname);}</script>";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname    = trim($_POST['fullname'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = trim($_POST['password'] ?? '');
    $lvl         = trim($_POST['lvl'] ?? '');
    $supplier_id = isset($_POST['supplier_id']) && !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : null;
    $sales_id    = isset($_POST['sales_id'])    && !empty($_POST['sales_id'])    ? (int) $_POST['sales_id']    : null;

    if (empty($fullname) || empty($email) || empty($password) || empty($lvl)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $email = strtolower($email);
        $registrationSuccess = false;

        try {
            $checkEmail = $conn->prepare("SELECT id FROM nobleaccount WHERE LOWER(email) = LOWER(?)");
            $checkEmail->bind_param("s", $email);
            $checkEmail->execute();

            if ($checkEmail->get_result()->num_rows > 0) {
                $error = "This email is already registered.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                if ($lvl === 'supplier') {
                    if (empty($supplier_id)) {
                        $error = "Please enter a Supplier ID.";
                    } else {
                        $check = $conn->prepare("SELECT id FROM nobleaccount WHERE supplier_id = ?");
                        $check->bind_param("i", $supplier_id);
                        $check->execute();
                        if ($check->get_result()->num_rows > 0) {
                            $error = "Supplier ID #$supplier_id is already linked to an account.";
                        } else {
                            $stmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl, supplier_id) VALUES (?, ?, ?, ?, ?)");
                            $stmt->bind_param("ssssi", $fullname, $email, $hashed_password, $lvl, $supplier_id);
                            $registrationSuccess = $stmt->execute();
                            if (!$registrationSuccess) $error = "Registration failed. Please try again.";
                            $stmt->close();
                        }
                        $check->close();
                    }
                } elseif ($lvl === 'sales') {
                    if (empty($sales_id)) {
                        $error = "Please enter a Sales ID.";
                    } else {
                        $check = $conn->prepare("SELECT id FROM nobleaccount WHERE sales_id = ?");
                        $check->bind_param("i", $sales_id);
                        $check->execute();
                        if ($check->get_result()->num_rows > 0) {
                            $error = "Sales ID #$sales_id is already linked to an account.";
                        } else {
                            $stmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl, sales_id) VALUES (?, ?, ?, ?, ?)");
                            $stmt->bind_param("ssssi", $fullname, $email, $hashed_password, $lvl, $sales_id);
                            $registrationSuccess = $stmt->execute();
                            if (!$registrationSuccess) $error = "Registration failed. Please try again.";
                            $stmt->close();
                        }
                        $check->close();
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $fullname, $email, $hashed_password, $lvl);
                    $registrationSuccess = $stmt->execute();
                    if (!$registrationSuccess) $error = "Registration failed. Please try again.";
                    $stmt->close();
                }
            }
            $checkEmail->close();
        } catch (Exception $e) {
            $error = "An error occurred. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }

        if ($registrationSuccess && empty($error)) {
            header("Location: " . BASE_URL . "/account?success=true");
            exit();
        }
    }
}

if (isset($conn) && $conn) $conn->close();

// Helper to retain POST values
function old($key, $success) {
    if (!empty($success)) return '';
    return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : '';
}
function selected($key, $val) {
    return (isset($_POST[$key]) && $_POST[$key] === $val) ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Registration — Noble Enterprise</title>
</head>

<body class="min-h-screen bg-gray-50">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- Page wrapper -->
    <div class="flex items-start justify-center px-4 py-10">
        <div class="w-full max-w-md">

            <!-- Page title -->
            <div class="mb-6 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-orange-100 mb-3">
                    <i class="fas fa-user-plus text-orange-500 text-lg"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">Create Account</h1>
                <p class="text-sm text-gray-500 mt-1">Noble Homedepot Management System</p>
            </div>

            <!-- Alert: Error -->
            <?php if (!empty($error)): ?>
            <div id="alert-error" class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm">
                <i class="fas fa-circle-exclamation mt-0.5 text-red-400"></i>
                <span class="flex-1"><?= htmlspecialchars($error) ?></span>
                <button onclick="dismissAlert('alert-error')" class="text-red-400 hover:text-red-600 text-base leading-none">&times;</button>
            </div>
            <?php endif; ?>

            <!-- Alert: Success -->
            <?php if (!empty($success)): ?>
            <div id="alert-success" class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm">
                <i class="fas fa-circle-check mt-0.5 text-green-400"></i>
                <span class="flex-1"><?= htmlspecialchars($success) ?></span>
                <button onclick="dismissAlert('alert-success')" class="text-green-400 hover:text-green-600 text-base leading-none">&times;</button>
            </div>
            <?php endif; ?>

            <!-- Card -->
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-7">
                <form method="POST" novalidate class="space-y-5">

                    <!-- Full Name -->
                    <div>
                        <label for="fullname" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" id="fullname" name="fullname" required
                            value="<?= old('fullname', $success) ?>"
                            placeholder="e.g. Juan dela Cruz"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition">
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" id="email" name="email" required
                            value="<?= old('email', $success) ?>"
                            placeholder="you@example.com"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition">
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required minlength="6"
                                placeholder="At least 6 characters"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 pr-10 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition">
                            <button type="button" onclick="togglePassword()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm">
                                <i id="pw-icon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Account Level -->
                    <div>
                        <label for="lvl-select" class="block text-sm font-medium text-gray-700 mb-1">Account Role</label>
                        <select id="lvl-select" name="lvl" required
                            onchange="onRoleChange(this.value)"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition bg-white">
                            <option value="">— Select a role —</option>
                            <option value="superadmin"      <?= selected('lvl','superadmin') ?>>Super Administrator</option>
                            <option value="sales"           <?= selected('lvl','sales') ?>>Sales Representative</option>
                            <option value="productspecialist" <?= selected('lvl','productspecialist') ?>>Product Specialist</option>
                            <option value="accountant"      <?= selected('lvl','accountant') ?>>Accountant</option>
                            <option value="logistic"        <?= selected('lvl','logistic') ?>>Logistics Coordinator</option>
                            <option value="warehouse"       <?= selected('lvl','warehouse') ?>>Warehouse Manager</option>
                            <option value="hr"              <?= selected('lvl','hr') ?>>Human Resources</option>
                        </select>
                    </div>

                    <!-- Sales ID (shown only when role = sales) -->
                    <div id="field-sales"
                        class="<?= (isset($_POST['lvl']) && $_POST['lvl'] === 'sales') ? '' : 'hidden' ?>">
                        <label for="sales_id" class="block text-sm font-medium text-gray-700 mb-1">Sales ID Number</label>
                        <input type="number" id="sales_id" name="sales_id" min="1"
                            value="<?= old('sales_id', $success) ?>"
                            placeholder="Enter your Sales ID"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition">
                    </div>

                    <!-- Supplier ID (shown only when role = supplier) -->
                    <div id="field-supplier"
                        class="<?= (isset($_POST['lvl']) && $_POST['lvl'] === 'supplier') ? '' : 'hidden' ?>">
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-1">Supplier ID Number</label>
                        <input type="number" id="supplier_id" name="supplier_id" min="1"
                            value="<?= old('supplier_id', $success) ?>"
                            placeholder="Enter your Supplier ID"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition">
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                        class="w-full bg-orange-400 hover:bg-orange-500 active:bg-orange-600 text-white font-semibold rounded-lg py-2.5 text-sm transition mt-1">
                        <i class="fas fa-user-plus mr-2"></i>Create Account
                    </button>

                </form>
            </div>

            <!-- Footer note -->
            <p class="text-center text-xs text-gray-400 mt-5">
                <i class="fas fa-shield-halved mr-1"></i>Your information is kept secure and private.
            </p>

        </div>
    </div>

    <script>
        function onRoleChange(role) {
            const salesField    = document.getElementById('field-sales');
            const supplierField = document.getElementById('field-supplier');
            const salesInput    = document.getElementById('sales_id');
            const supplierInput = document.getElementById('supplier_id');

            salesField.classList.toggle('hidden', role !== 'sales');
            supplierField.classList.toggle('hidden', role !== 'supplier');

            salesInput.required    = (role === 'sales');
            supplierInput.required = (role === 'supplier');

            if (role !== 'sales')     salesInput.value    = '';
            if (role !== 'supplier')  supplierInput.value = '';
        }

        function togglePassword() {
            const pw   = document.getElementById('password');
            const icon = document.getElementById('pw-icon');
            const show = pw.type === 'password';
            pw.type         = show ? 'text' : 'password';
            icon.className  = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        function dismissAlert(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.transition = 'opacity 0.2s';
                el.style.opacity    = '0';
                setTimeout(() => el.remove(), 200);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Restore role-dependent fields on page load (POST error case)
            const role = document.getElementById('lvl-select').value;
            if (role) onRoleChange(role);

            // Auto-dismiss alerts after 5 s
            ['alert-error', 'alert-success'].forEach(id => {
                const el = document.getElementById(id);
                if (el) setTimeout(() => dismissAlert(id), 5000);
            });
        });
    </script>

</body>
</html>