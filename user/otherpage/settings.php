<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

$notification = ""; // For success/error messages

// ✅ Restore session from remember_token (if not already logged in)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// ✅ If form is submitted, insert/update directly
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sex        = $_POST['sex'];
    $birthplace = $_POST['birthplace'];
    $birthdate  = $_POST['birthdate'];
    $occupation = $_POST['occupation'];
    $mobile     = $_POST['mobile']; // Mobile will be updated in users table

    // Check if mobile number is already used by another user
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
    $stmt_check->bind_param("si", $mobile, $_SESSION['user_id']);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();

    if ($check_result->num_rows > 0) {
        // Mobile number already exists for another user
        $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                            ❌ This mobile number is already registered to another account. Please use a different number.
                         </div>";
    } else {
        // Safe to update mobile in users table
        try {
            $stmt_mobile = $conn->prepare("UPDATE users SET mobile = ? WHERE id = ?");
            $stmt_mobile->bind_param("si", $mobile, $_SESSION['user_id']);
            $stmt_mobile->execute();
            $stmt_mobile->close();

            // Check if record exists in user_details
            $stmt = $conn->prepare("SELECT user_id FROM user_details WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                // Update user_details (without mobile)
                $sql = "UPDATE user_details 
                        SET sex=?, birthplace=?, birthdate=?, occupation=?, is_verified=0 
                        WHERE user_id=?";
                $stmt2 = $conn->prepare($sql);
                $stmt2->bind_param("ssssi", $sex, $birthplace, $birthdate, $occupation, $_SESSION['user_id']);
                $stmt2->execute();
                $stmt2->close();
                $notification = "<div class='mb-4 p-3 bg-blue-100 border-l-4 border-blue-500 text-blue-700 rounded'>
                                     Profile updated successfully! Waiting for admin verification.
                                 </div>";
            } else {
                // Insert user_details (without mobile)
                $sql = "INSERT INTO user_details (user_id, sex, birthplace, birthdate, occupation, is_verified) 
                        VALUES (?, ?, ?, ?, ?, 0)";
                $stmt2 = $conn->prepare($sql);
                $stmt2->bind_param("issss", $_SESSION['user_id'], $sex, $birthplace, $birthdate, $occupation);
                $stmt2->execute();
                $stmt2->close();
                $notification = "<div class='mb-4 p-3 bg-green-100 border-l-4 border-green-500 text-green-700 rounded'>
                                    🎉 Profile created successfully! Waiting for admin verification.
                                 </div>";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // Handle any database errors
            $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                                ❌ An error occurred while updating your profile. Please try again.
                             </div>";
        }
    }
    $stmt_check->close();
}

// ✅ Fetch existing details and mobile from users table
$detail = [
    'sex' => '',
    'birthplace' => '',
    'birthdate' => '',
    'occupation' => '',
    'is_verified' => 0
];

$stmt = $conn->prepare("SELECT * FROM user_details WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $detail = $res->fetch_assoc();
}
$stmt->close();

// Get mobile from users table
$mobile_value = '';
$stmt_user = $conn->prepare("SELECT mobile FROM users WHERE id = ?");
$stmt_user->bind_param("i", $_SESSION['user_id']);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $mobile_value = $user_data['mobile'] ?? '';
}
$stmt_user->close();

// Check completion status
$fields_completed = 0;
$total_fields = 5;
$required_fields = ['sex', 'birthplace', 'birthdate', 'occupation'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!empty($detail[$field] ?? '')) {
        $fields_completed++;
    } else {
        $missing_fields[] = ucfirst($field);
    }
}

// Check mobile separately from users table
if (!empty($mobile_value)) {
    $fields_completed++;
} else {
    $missing_fields[] = 'Mobile';
}

$completion_percentage = ($fields_completed / $total_fields) * 100;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .progress-bar {
            transition: width 0.3s ease-in-out;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen ">
    <?php include '../navbar/top.php'; ?>


    <div class="container mx-auto px-4 max-w-4xl">

        <!-- Profile Header Card -->
        <div class="bg-white shadow-lg p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <!-- Left: Avatar and Basic Info -->
                <div class="flex items-center gap-4">
                    <?php if (!empty($_SESSION['user_picture'])): ?>
                        <img src="<?= htmlspecialchars($_SESSION['user_picture']) ?>"
                            class="w-20 h-20 rounded-full border-4 border-orange-200" alt="Profile Picture">
                    <?php else: ?>
                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                            <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                            <?= htmlspecialchars($_SESSION['user_name']) ?>
                            <?php if ($detail['is_verified'] == 1): ?>
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 shadow-lg" title="Verified Account">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </h1>
                        <p class="text-gray-600 text-lg"><?= htmlspecialchars($_SESSION['user_email']) ?></p>
                    </div>
                </div>

                <!-- Right: Profile Completion Progress -->
                <div class="bg-gray-50 p-4 rounded-lg shadow-inner">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Profile Completion</h3>
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                        <div class="progress-bar bg-gradient-to-r from-orange-400 to-orange-600 h-3 rounded-full shadow-sm"
                            style="width: <?= $completion_percentage ?>%"></div>
                    </div>
                    <p class="text-sm text-gray-600">
                        <?= $fields_completed ?>/<?= $total_fields ?> fields completed (<?= round($completion_percentage) ?>%)
                    </p>
                </div>
            </div>
        </div>

     <!-- Notifications -->
<?php if ($notification): ?>
    <div id="notification-container">
        <?= $notification ?>
    </div>

    <script>
        // Auto-hide after 3 seconds (3000 ms)
        setTimeout(() => {
            const notif = document.getElementById('notification-container');
            if (notif) {
                notif.style.transition = 'opacity 0.5s ease';
                notif.style.opacity = '0';
                setTimeout(() => notif.remove(), 500); // remove from DOM after fade-out
            }
        }, 3000);
    </script>
<?php endif; ?>


        <!-- Verification Status Card -->
        <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
            <?php if ($detail['is_verified'] == 1): ?>
                <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-green-800">Account Verified!</h3>
                        <p class="text-green-700 text-sm">Your profile has been approved by our admin team.</p>
                    </div>
                </div>
            <?php elseif ($completion_percentage == 100): ?>
                <div class="flex items-center gap-3 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-yellow-800">Pending Verification</h3>
                        <p class="text-yellow-700 text-sm">Your profile is complete and waiting for admin approval.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-blue-800">Complete Your Profile 📝</h3>
                        <p class="text-blue-700 text-sm">
                            Please fill in the missing information:
                            <span class="font-medium"><?= implode(', ', $missing_fields) ?></span>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Split Layout: Form and Current Information -->
        <div class="grid lg:grid-cols-2 gap-6">

            <!-- LEFT SIDE: Profile Form -->
            <div class="bg-white shadow-lg rounded-lg p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Update Profile Information</h2>
                        <p class="text-sm text-gray-600">Fill out the form to complete your profile</p>
                    </div>
                </div>

                <form method="POST" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sex *</label>
                        <select name="sex" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <option value="">Select your sex</option>
                            <option value="male" <?= $detail['sex'] === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $detail['sex'] === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= $detail['sex'] === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Mobile Number *</label>
                        <input type="tel" name="mobile" value="<?= htmlspecialchars($mobile_value) ?>"
                            placeholder="e.g., 09123456789"
                            pattern="[0-9]{11}" maxlength="11" required
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,11)"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                        <p class="text-xs text-gray-500 mt-1">Enter your 11-digit mobile number (must be unique)</p>
                    </div>


                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Birthplace *</label>
                        <input type="text" name="birthplace" value="<?= htmlspecialchars($detail['birthplace']) ?>"
                            placeholder="e.g., Manila, Philippines" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Birthdate *</label>
                        <input type="date" name="birthdate" value="<?= htmlspecialchars($detail['birthdate']) ?>"
                            max="<?= date('Y-m-d') ?>" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Occupation *</label>
                        <input type="text" name="occupation" value="<?= htmlspecialchars($detail['occupation']) ?>"
                            placeholder="e.g., Software Developer, Teacher, Student" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <div class="pt-4">
                        <button type="submit"
                            class="w-full bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold py-4 px-6 rounded-lg shadow-lg transform transition duration-200 hover:scale-[1.02] focus:outline-none focus:ring-4 focus:ring-orange-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Update Profile Information
                        </button>
                        <p class="text-center text-sm text-gray-500 mt-3">
                            * All fields are required for verification
                        </p>
                    </div>
                </form>
            </div>

            <!-- RIGHT SIDE: Current Information Display -->
            <div class="bg-white shadow-lg rounded-lg p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Your Current Information</h2>
                        <p class="text-sm text-gray-600">Review your profile details below</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- Basic Info Section -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Basic Information</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border-l-4 border-blue-500">
                                <span class="font-medium text-gray-700">Full Name:</span>
                                <span class="text-gray-900 font-semibold"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border-l-4 border-blue-500">
                                <span class="font-medium text-gray-700">Email:</span>
                                <span class="text-gray-900"><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Details Section -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Profile Details</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 <?= empty($detail['sex']) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">Sex:</span>
                                <span class="<?= empty($detail['sex']) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($detail['sex']) ? ' Not provided' : ' ' . ucfirst(htmlspecialchars($detail['sex'])) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center p-3 <?= empty($mobile_value) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">Mobile:</span>
                                <span class="<?= empty($mobile_value) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($mobile_value) ? ' Not provided' : ' ' . htmlspecialchars($mobile_value) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center p-3 <?= empty($detail['birthdate']) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">Birthdate:</span>
                                <span class="<?= empty($detail['birthdate']) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($detail['birthdate']) ? ' Not provided' : ' ' . date('F j, Y', strtotime($detail['birthdate'])) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center p-3 <?= empty($detail['birthplace']) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">Birthplace:</span>
                                <span class="<?= empty($detail['birthplace']) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($detail['birthplace']) ? ' Not provided' : ' ' . htmlspecialchars($detail['birthplace']) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center p-3 <?= empty($detail['occupation']) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">Occupation:</span>
                                <span class="<?= empty($detail['occupation']) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($detail['occupation']) ? ' Not provided' : ' ' . htmlspecialchars($detail['occupation']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Summary -->
                    <div class="mt-6 p-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-lg border border-orange-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-orange-800">Completion Status</h4>
                                <p class="text-sm text-orange-700">
                                    <?= $fields_completed ?> of <?= $total_fields ?> fields completed
                                </p>
                            </div>
                            <div class="text-2xl font-bold text-orange-600">
                                <?= round($completion_percentage) ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Simple form validation for mobile number
        document.querySelector('input[name="mobile"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            if (value.length > 11) {
                value = value.slice(0, 11); // Limit to 11 digits
            }
            e.target.value = value;
        });

        // Show success message after form submission
        <?php if ($notification): ?>
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        <?php endif; ?>
    </script>

</body>

</html>