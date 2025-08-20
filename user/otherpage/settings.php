<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

$notification = ""; // For success/error messages
$form_submitted = false; // Track if form was submitted

// reCAPTCHA Secret Key - REPLACE WITH YOUR ACTUAL SECRET KEY
$recaptcha_secret = '6LeYkKkrAAAAAMZEkXSwOW1fryAOATmycKvAaNxq';

// Function to verify reCAPTCHA
function verifyRecaptcha($recaptcha_response, $secret_key)
{
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secret_key,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result, true);

    return isset($response['success']) && $response['success'] === true;
}

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

// Get current user details first to check if government ID exists
$detail = [
    'sex' => '',
    'birthplace' => '',
    'birthdate' => '',
    'occupation' => '',
    'id_type' => '',
    'government_id_path' => '',
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

// ✅ Handle file upload and form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_submitted = true; // Mark that form was submitted

    // FIRST: Verify reCAPTCHA
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptcha_response) || !verifyRecaptcha($recaptcha_response, $recaptcha_secret)) {
        $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                             Please complete the reCAPTCHA verification to continue.
                         </div>";
    } else {
        // reCAPTCHA verified, proceed with form processing
        $sex        = $_POST['sex'];
        $birthplace = $_POST['birthplace'];
        $birthdate  = $_POST['birthdate'];
        $occupation = $_POST['occupation'];
        $mobile     = $_POST['mobile'];
        $id_type    = $_POST['id_type'];

        // Handle file upload
        $government_id_path = null;
        $upload_error = false;
        $file_uploaded_now = false;

        // Check if user is uploading a new file
        if (isset($_FILES['government_id']) && $_FILES['government_id']['error'] === UPLOAD_ERR_OK) {
            $file_uploaded_now = true;
            $upload_dir = '../../uploads/government_ids/';

            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_info = pathinfo($_FILES['government_id']['name']);
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_extension = strtolower($file_info['extension']);

            // Validate file type (only allow JPG, JPEG, PNG - exclude PDF and GIF)
            if (!in_array($file_extension, $allowed_extensions)) {
                $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                                     Invalid file type. Please upload JPG, JPEG, or PNG files only. PDF and GIF files are not allowed.
                                 </div>";
                $upload_error = true;
            }

            // Validate file size (5MB max)
            elseif ($_FILES['government_id']['size'] > 5 * 1024 * 1024) {
                $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                                     File size too large. Maximum size is 5MB.
                                 </div>";
                $upload_error = true;
            }

            // Generate unique filename
            if (!$upload_error) {
                $unique_filename = 'gov_id_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $target_path = $upload_dir . $unique_filename;

                if (move_uploaded_file($_FILES['government_id']['tmp_name'], $target_path)) {
                    $government_id_path = $unique_filename; // Store relative path
                } else {
                    $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                                         Failed to upload file. Please try again.
                                     </div>";
                    $upload_error = true;
                }
            }
        }

        // ✅ CRITICAL CHECK: Ensure government ID exists (either uploaded now or previously)
        if (!$file_uploaded_now && empty($detail['government_id_path'])) {
            $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                                 <div class='flex items-center gap-2'>
                                     <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                         <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/>
                                     </svg>
                                     <strong>Government ID Required:</strong> Please upload your government ID before submitting the form.
                                 </div>
                             </div>";
            $upload_error = true;
        }

        // Only proceed with database operations if no upload error occurred
        if (!$upload_error) {
            // Check if mobile number is already used by another user
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
            $stmt_check->bind_param("si", $mobile, $_SESSION['user_id']);
            $stmt_check->execute();
            $check_result = $stmt_check->get_result();

            if ($check_result->num_rows > 0) {
                // Mobile number already exists for another user
                $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                                     This mobile number is already registered to another account. Please use a different number.
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
                        // Update user_details
                        if ($file_uploaded_now && $government_id_path) {
                            // Delete old government ID file if exists
                            $stmt_old = $conn->prepare("SELECT government_id_path FROM user_details WHERE user_id = ?");
                            $stmt_old->bind_param("i", $_SESSION['user_id']);
                            $stmt_old->execute();
                            $old_result = $stmt_old->get_result();
                            if ($old_result->num_rows > 0) {
                                $old_data = $old_result->fetch_assoc();
                                if ($old_data['government_id_path'] && file_exists($upload_dir . $old_data['government_id_path'])) {
                                    unlink($upload_dir . $old_data['government_id_path']);
                                }
                            }
                            $stmt_old->close();

                            $sql = "UPDATE user_details 
                                    SET sex=?, birthplace=?, birthdate=?, occupation=?, id_type=?, government_id_path=?, is_verified=0 
                                    WHERE user_id=?";
                            $stmt2 = $conn->prepare($sql);
                            $stmt2->bind_param("ssssssi", $sex, $birthplace, $birthdate, $occupation, $id_type, $government_id_path, $_SESSION['user_id']);
                        } else {
                            // No new file uploaded, keep existing government_id_path
                            $sql = "UPDATE user_details 
                                    SET sex=?, birthplace=?, birthdate=?, occupation=?, id_type=?, is_verified=0 
                                    WHERE user_id=?";
                            $stmt2 = $conn->prepare($sql);
                            $stmt2->bind_param("sssssi", $sex, $birthplace, $birthdate, $occupation, $id_type, $_SESSION['user_id']);
                        }
                        $stmt2->execute();
                        $stmt2->close();
                        $notification = "<div class='mb-4 p-3 bg-blue-100 border-l-4 border-blue-500 text-blue-700 rounded'>
                                             ✅ Profile updated successfully! Waiting for admin verification.
                                         </div>";

                        // SUCCESS: Redirect to prevent resubmission and reset reCAPTCHA
                        $_SESSION['success_message'] = $notification;
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        // Insert user_details - government_id_path is required for new records
                        if (!$government_id_path) {
                            $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                                                 Government ID upload is required for new profile creation.
                                             </div>";
                        } else {
                            $sql = "INSERT INTO user_details (user_id, sex, birthplace, birthdate, occupation, id_type, government_id_path, is_verified) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
                            $stmt2 = $conn->prepare($sql);
                            $stmt2->bind_param("issssss", $_SESSION['user_id'], $sex, $birthplace, $birthdate, $occupation, $id_type, $government_id_path);
                            $stmt2->execute();
                            $stmt2->close();
                            $notification = "<div class='mb-4 p-3 bg-green-100 border-l-4 border-green-500 text-green-700 rounded'>
                                                 Profile created successfully! Waiting for admin verification.
                                             </div>";

                            // SUCCESS: Redirect to prevent resubmission and reset reCAPTCHA
                            $_SESSION['success_message'] = $notification;
                            header('Location: ' . $_SERVER['PHP_SELF']);
                            exit;
                        }
                    }
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    // Handle any database errors
                    $notification = "<div class='mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded'>
                                         An error occurred while updating your profile. Please try again.
                                     </div>";
                }
            }
            $stmt_check->close();
        }
    }
}

// Check for success message from redirect
if (isset($_SESSION['success_message'])) {
    $notification = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Rest of your existing code for fetching details...
$detail = [
    'sex' => '',
    'birthplace' => '',
    'birthdate' => '',
    'occupation' => '',
    'id_type' => '',
    'government_id_path' => '',
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

// Check completion status (now including ID type and government ID)
$fields_completed = 0;
$total_fields = 7; // Updated total fields
$required_fields = ['sex', 'birthplace', 'birthdate', 'occupation', 'id_type'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!empty($detail[$field] ?? '')) {
        $fields_completed++;
    } else {
        $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
    }
}

// Check mobile separately from users table
if (!empty($mobile_value)) {
    $fields_completed++;
} else {
    $missing_fields[] = 'Mobile';
}

// Check government ID
if (!empty($detail['government_id_path'])) {
    $fields_completed++;
} else {
    $missing_fields[] = 'Government ID';
}

$completion_percentage = ($fields_completed / $total_fields) * 100;

// Government ID types
$id_types = [
    'drivers_license' => "Driver's License",
    'passport' => 'Passport',
    'sss_id' => 'SSS ID',
    'philhealth_id' => 'PhilHealth ID',
    'tin_id' => 'TIN ID',
    'postal_id' => 'Postal ID',
    'voters_id' => "Voter's ID",
    'prc_id' => 'PRC ID',
    'senior_citizen_id' => 'Senior Citizen ID',
    'pwd_id' => 'PWD ID',
    'umid' => 'UMID',
    'national_id' => 'National ID (PhilSys)',
    'other' => 'Other Valid Government ID'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- reCAPTCHA Script -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        .progress-bar {
            transition: width 0.3s ease-in-out;
        }

        .file-drop-area {
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .file-drop-area.dragover {
            border-color: #f59e0b;
            background-color: #fffbeb;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 0.5rem;
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
                // Auto-hide after 5 seconds (5000 ms)
                setTimeout(() => {
                    const notif = document.getElementById('notification-container');
                    if (notif) {
                        notif.style.transition = 'opacity 0.5s ease';
                        notif.style.opacity = '0';
                        setTimeout(() => notif.remove(), 500); // remove from DOM after fade-out
                    }
                }, 5000);
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

                <form method="POST" enctype="multipart/form-data" class="space-y-5" id="profileForm">
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

                    <!-- Government ID Type -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Government ID Type *</label>
                        <select name="id_type" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <option value="">Select ID Type</option>
                            <?php foreach ($id_types as $key => $value): ?>
                                <option value="<?= $key ?>" <?= $detail['id_type'] === $key ? 'selected' : '' ?>>
                                    <?= $value ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Government ID Upload -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Upload Government ID *
                            <span class="text-xs text-gray-500 font-normal">(JPG, JPEG, PNG only - Max 5MB)</span>
                        </label>

                        <div class="file-drop-area p-6 text-center bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:bg-gray-100 transition-all duration-300"
                            onclick="document.getElementById('government_id').click()">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <p class="text-sm text-gray-600 mb-2">
                                    <span class="font-semibold text-orange-600">Click to upload</span> or drag and drop
                                </p>
                                <p class="text-xs text-gray-500">JPG, JPEG, PNG only - up to 5MB</p>
                                <p class="text-xs text-red-500 mt-1">⚠️ PDF and GIF files are not allowed</p>
                            </div>
                        </div>

                        <input type="file" id="government_id" name="government_id" accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                            class="hidden" onchange="previewFile(this)" required>

                        <!-- File Preview -->
                        <div id="file-preview" class="mt-3 hidden">
                            <div class="flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span id="file-name" class="text-sm font-medium text-green-800"></span>
                                <button type="button" onclick="removeFile()" class="ml-auto text-red-500 hover:text-red-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- File Type Warning -->
                        <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <div>
                                    <h4 class="text-sm font-semibold text-yellow-800">Allowed File Types</h4>
                                    <ul class="text-xs text-yellow-700 mt-1 list-disc list-inside">
                                        <li>✅ JPG/JPEG images</li>
                                        <li>✅ PNG images</li>
                                        <li>all documents (not allowed)</li>
                                        <li>GIF images (not allowed)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Current ID Display -->
                        <?php if (!empty($detail['government_id_path'])): ?>
                            <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-sm font-medium text-blue-800">Current ID uploaded</span>
                                    </div>
                                    <a href="../../uploads/government_ids/<?= htmlspecialchars($detail['government_id_path']) ?>"
                                        target="_blank"
                                        class="text-blue-600 hover:text-blue-800 text-sm underline">
                                        View
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- reCAPTCHA Widget 6LeYkKkrAAAAADQDCnPgE96F0kx8XE4Co9apl91U -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Security Verification *</label>
                        <div class="p-4 bg-gray-50 rounded-lg border">
                            <div class="g-recaptcha" data-sitekey="6LeYkKkrAAAAADQDCnPgE96F0kx8XE4Co9apl91U"></div>
                            <div id="recaptcha-error" class="text-red-600 text-sm mt-2 hidden">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Please complete the reCAPTCHA verification to continue.
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Please verify that you are not a robot before submitting the form.</p>
                    </div>

                    <div class="pt-4">
                        <button type="submit" id="submitButton"
                            class="w-full bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold py-4 px-6 rounded-lg shadow-lg transform transition duration-200 hover:scale-[1.02] focus:outline-none focus:ring-4 focus:ring-orange-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" require>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span id="submitText">Update Profile Information</span>
                        </button>
                        <p class="text-center text-sm text-gray-500 mt-3">
                            * All fields including reCAPTCHA are required for verification
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
                                    <?= empty($detail['sex']) ? 'Not provided' : ucfirst(htmlspecialchars($detail['sex'])) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center p-3 <?= empty($mobile_value) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">Mobile:</span>
                                <span class="<?= empty($mobile_value) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($mobile_value) ? 'Not provided' : htmlspecialchars($mobile_value) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center p-3 <?= empty($detail['birthdate']) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">Birthdate:</span>
                                <span class="<?= empty($detail['birthdate']) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($detail['birthdate']) ? 'Not provided' : date('F j, Y', strtotime($detail['birthdate'])) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center p-3 <?= empty($detail['birthplace']) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">Birthplace:</span>
                                <span class="<?= empty($detail['birthplace']) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($detail['birthplace']) ? 'Not provided' : htmlspecialchars($detail['birthplace']) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center p-3 <?= empty($detail['occupation']) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">Occupation:</span>
                                <span class="<?= empty($detail['occupation']) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($detail['occupation']) ? 'Not provided' : htmlspecialchars($detail['occupation']) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center p-3 <?= empty($detail['id_type']) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <span class="font-medium text-gray-700">ID Type:</span>
                                <span class="<?= empty($detail['id_type']) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                    <?= empty($detail['id_type']) ? 'Not provided' : $id_types[$detail['id_type']] ?? ucfirst(str_replace('_', ' ', $detail['id_type'])) ?>
                                </span>
                            </div>

                            <div class="p-3 <?= empty($detail['government_id_path']) ? 'bg-red-50 border-l-4 border-red-400' : 'bg-green-50 border-l-4 border-green-400' ?> rounded-lg">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-medium text-gray-700">Government ID:</span>
                                    <span class="<?= empty($detail['government_id_path']) ? 'text-red-600 font-medium' : 'text-green-700 font-semibold' ?>">
                                        <?= empty($detail['government_id_path']) ? 'Not uploaded' : 'Uploaded' ?>
                                    </span>
                                </div>
                                <?php if (!empty($detail['government_id_path'])): ?>
                                    <div class="mt-2">
                                        <?php
                                        $file_extension = pathinfo($detail['government_id_path'], PATHINFO_EXTENSION);
                                        $is_image = in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png']);
                                        ?>
                                        <?php if ($is_image): ?>
                                            <img src="../../uploads/government_ids/<?= htmlspecialchars($detail['government_id_path']) ?>"
                                                alt="Government ID"
                                                class="preview-image border shadow-sm">
                                        <?php else: ?>
                                            <div class="flex items-center gap-2 p-2 bg-white rounded border">
                                                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span class="text-sm font-medium">PDF Document</span>
                                            </div>
                                        <?php endif; ?>
                                        <a href="../../uploads/government_ids/<?= htmlspecialchars($detail['government_id_path']) ?>"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 mt-2 text-sm text-blue-600 hover:text-blue-800">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View Full Size
                                        </a>
                                    </div>
                                <?php endif; ?>
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

                    <!-- reCAPTCHA Info Panel -->
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.5-1.5a8.97 8.97 0 00-4.5-1.2c-5 0-9 4-9 9s4 9 9 9 9-4 9-9c0-1.6-.4-3.1-1.1-4.4" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-blue-800 text-sm">Security Verification Required</h4>
                                <p class="text-blue-700 text-sm mt-1">
                                    Complete the reCAPTCHA verification to ensure your form submission is secure and prevent automated submissions.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof grecaptcha !== 'undefined') {
                setTimeout(function() {
                    try {
                        grecaptcha.reset();
                    } catch (e) {
                        console.log('reCAPTCHA reset failed:', e);
                    }
                }, 100);
            }
        });


        // reCAPTCHA validation function
        function validateRecaptcha() {
            const recaptchaResponse = grecaptcha.getResponse();
            const errorDiv = document.getElementById('recaptcha-error');

            if (recaptchaResponse.length === 0) {
                errorDiv.classList.remove('hidden');
                return false;
            } else {
                errorDiv.classList.add('hidden');
                return true;
            }
        }

        // Form submission handler with reCAPTCHA validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const submitButton = document.getElementById('submitButton');
            const submitText = document.getElementById('submitText');

            if (!validateRecaptcha()) {
                e.preventDefault();
                // Scroll to reCAPTCHA for user attention
                document.querySelector('.g-recaptcha').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return false;
            }

            // Show loading state
            submitButton.disabled = true;
            submitText.textContent = 'Updating Profile...';

            // Let form submit naturally after validation passes
            return true;
        });

        // Reset reCAPTCHA error when user completes it
        function recaptchaCallback() {
            document.getElementById('recaptcha-error').classList.add('hidden');
        }

        // File upload functionality
        function previewFile(input) {
            const file = input.files[0];
            const preview = document.getElementById('file-preview');
            const fileName = document.getElementById('file-name');

            if (file) {
                fileName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                preview.classList.remove('hidden');

                // Update drop area appearance
                const dropArea = document.querySelector('.file-drop-area');
                dropArea.classList.add('border-green-400', 'bg-green-50');
                dropArea.classList.remove('border-gray-300', 'bg-gray-50');
            }
        }

        function removeFile() {
            const input = document.getElementById('government_id');
            const preview = document.getElementById('file-preview');
            const dropArea = document.querySelector('.file-drop-area');

            input.value = '';
            preview.classList.add('hidden');

            // Reset drop area appearance
            dropArea.classList.remove('border-green-400', 'bg-green-50');
            dropArea.classList.add('border-gray-300', 'bg-gray-50');
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Drag and drop functionality
        const dropArea = document.querySelector('.file-drop-area');
        const fileInput = document.getElementById('government_id');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        dropArea.addEventListener('drop', handleDrop, false);

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight(e) {
            dropArea.classList.add('dragover');
        }

        function unhighlight(e) {
            dropArea.classList.remove('dragover');
        }

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                fileInput.files = files;
                previewFile(fileInput);
            }
        }

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

        // Reset reCAPTCHA error when user completes it
        function recaptchaCallback() {
            document.getElementById('recaptcha-error').classList.add('hidden');
        }
    </script>

</body>

</html>