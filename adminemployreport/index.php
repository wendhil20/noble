<?php
session_name("nobleemployeereport");
session_start();
include '../connection/connect.php';

$message = '';
$messageType = '';

// Reset auto-increment if needed
$tables = ['employeaccountreport'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];

    if ($max_id > 0) {
        $result2 = $conn->query("SELECT COUNT(*) AS count FROM $table WHERE id = $max_id");
        $row2 = $result2->fetch_assoc();
        if ((int)$row2['count'] === 0) {
            $conn->query("ALTER TABLE $table AUTO_INCREMENT = $max_id");
        }
    } else {
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $pass = $_POST['password'];
    $confirmPass = $_POST['confirm_password'];

    if ($pass !== $confirmPass) {
        $_SESSION['flash_message'] = "Passwords do not match!";
        $_SESSION['flash_type'] = "error";
    } elseif (strlen($pass) < 6) {
        $_SESSION['flash_message'] = "Password must be at least 6 characters!";
        $_SESSION['flash_type'] = "error";
    } else {
        $checkQuery = "SELECT * FROM employeaccountreport WHERE email = '$email'";
        $result = mysqli_query($conn, $checkQuery);

        if (mysqli_num_rows($result) > 0) {
            $_SESSION['flash_message'] = "Email already registered!";
            $_SESSION['flash_type'] = "error";
        } else {
            $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
        $insertQuery = "INSERT INTO employeaccountreport (username, position, email, password) 
                VALUES ('$user', '$position', '$email', '$hashedPass')";


            if (mysqli_query($conn, $insertQuery)) {
                $_SESSION['flash_message'] = "Registration successful! You can now login.";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Registration failed!";
                $_SESSION['flash_type'] = "error";
            }
        }
    }

    // Redirect to avoid resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass = $_POST['password'];

    $query = "SELECT * FROM employeaccountreport WHERE email = '$email'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];

            header("Location: mainreport.php");
            exit();
        } else {
            $_SESSION['flash_message'] = "Invalid email or password!";
            $_SESSION['flash_type'] = "error";
        }
    } else {
        $_SESSION['flash_message'] = "Invalid email or password!";
        $_SESSION['flash_type'] = "error";
    }

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Flash message system
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Report - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class=" min-h-screen flex items-center justify-center p-4">
    
    <div class=" w-full max-w-md p-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Employee Report</h1>
            <p class="text-gray-500 text-sm" id="formTitle">Login to your account</p>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" id="loginForm" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                <input type="email" name="email" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" name="password" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
            </div>

            <button type="submit" name="login" 
                class="w-full bg-black text-white font-semibold py-3 rounded-lg hover:from-purple-700 hover:to-pink-700 transition transform hover:scale-105 active:scale-95">
                Login
            </button>

            <p class="text-center text-sm text-gray-600">
                Don't have an account? 
                <a href="#" onclick="toggleForm(); return false;" class="text-black font-semibold hover:underline">Register here</a>
            </p>
        </form>

        <!-- Registration Form -->
        <form method="POST" id="registerForm" class="space-y-5 hidden">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input type="text" name="username" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
            </div>
            <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
        <input type="text" name="position" required 
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" 
            placeholder="e.g. Admin, Staff, Manager">
    </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                <input type="email" name="email" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" name="password" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                <input type="password" name="confirm_password" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
            </div>

            <button type="submit" name="register" 
                class="w-full bg-black text-white font-semibold py-3 rounded-lg hover:from-purple-700 hover:to-pink-700 transition transform hover:scale-105 active:scale-95">
                Register
            </button>

            <p class="text-center text-sm text-gray-600">
                Already have an account? 
                <a href="#" onclick="toggleForm(); return false;" class="text-purple-600 font-semibold hover:underline">Login here</a>
            </p>
        </form>
    </div>

    <script>
        function toggleForm() {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const formTitle = document.getElementById('formTitle');

            loginForm.classList.toggle('hidden');
            registerForm.classList.toggle('hidden');

            if (loginForm.classList.contains('hidden')) {
                formTitle.textContent = 'Create new account';
            } else {
                formTitle.textContent = 'Login to your account';
            }
        }
    </script>
</body>
</html>