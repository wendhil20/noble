<?php
include '../../connection/connect.php'; // adjust path if needed

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $lvl = trim($_POST['lvl']);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if email exists
    $check = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Email is already registered.";
    } else {
        // Insert into database with fullname and level
        $stmt = $conn->prepare("INSERT INTO nobleaccount (fullname, email, password, lvl) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullname, $email, $hashed_password, $lvl);

        if ($stmt->execute()) {
            $success = "Registration successful. You can now log in.";
        } else {
            $error = "Something went wrong. Try again.";
        }

        $stmt->close();
    }

    $check->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-blue-600">Register</h2>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div>
        <?php elseif ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="space-y-4">
            <!-- Full Name -->
            <input type="text" name="fullname" required class="w-full border px-4 py-2 rounded" placeholder="Full Name">

            <!-- Email -->
            <input type="email" name="email" required class="w-full border px-4 py-2 rounded" placeholder="Email">

            <!-- Password -->
            <input type="password" name="password" required class="w-full border px-4 py-2 rounded" placeholder="Password">

            <!-- Level (admin, superadmin, employee) -->
            <select name="lvl" required class="w-full border px-4 py-2 rounded">
                <option value="employee">Employee</option>
                <option value="admin">Admin</option>
                <option value="superadmin">Superadmin</option>
            </select>

            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded w-full">Register</button>
        </form>

        <p class="text-sm text-center mt-4">Already have an account?
            <a href="login.php" class="text-blue-600 hover:underline">Login</a>
        </p>
    </div>
</body>

</html>