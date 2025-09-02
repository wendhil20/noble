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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// ✅ NEW: Check if user is already verified - redirect to profile or dashboard
$verification_check = $conn->prepare("SELECT is_verified FROM user_details WHERE user_id = ?");
$verification_check->bind_param("i", $_SESSION['user_id']);
$verification_check->execute();
$verification_result = $verification_check->get_result();

if ($verification_result->num_rows > 0) {
    $verification_data = $verification_result->fetch_assoc();
    if ($verification_data['is_verified'] == 1) {
        // User is already verified, redirect them away from this page
        $_SESSION['verification_bypass_message'] = "<div class='mb-4 p-3 bg-green-100 border-l-4 border-green-500 text-green-700 rounded'>
                                                         Your account is already verified! You have been redirected to your profile.
                                                     </div>";
        header('Location: index.php'); // or wherever verified users should go
        exit;
    }
}
$verification_check->close();

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
            elseif ($_FILES['government_id']['size'] > 10 * 1024 * 1024) {
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

// ✅ NEW: Check for verification bypass message
if (isset($_SESSION['verification_bypass_message'])) {
    $notification = $_SESSION['verification_bypass_message'];
    unset($_SESSION['verification_bypass_message']);
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

// ✅ CRITICAL: Redirect verified users away from this page
if ($detail['is_verified'] == 1) {
    $_SESSION['verification_bypass_message'] = "<div class='mb-4 p-3 bg-green-100 border-l-4 border-green-500 text-green-700 rounded'>
                                                     <div class='flex items-center gap-2'>
                                                         <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                             <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'/>
                                                         </svg>
                                                         <strong>Account Already Verified:</strong> Your profile has been approved and you have full access to all features.
                                                     </div>
                                                 </div>";
    header('Location: index.php'); // Redirect to dashboard or main profile page
    exit;
}

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

// Check if profile is completely filled out
function isProfileComplete($detail, $mobile_value)
{
    $required_fields = [
        'sex' => $detail['sex'] ?? '',
        'birthplace' => $detail['birthplace'] ?? '',
        'birthdate' => $detail['birthdate'] ?? '',
        'occupation' => $detail['occupation'] ?? '',
        'id_type' => $detail['id_type'] ?? '',
        'government_id_path' => $detail['government_id_path'] ?? '',
        'mobile' => $mobile_value ?? ''
    ];

    // Check if all fields are filled
    foreach ($required_fields as $field => $value) {
        if (empty($value)) {
            return false;
        }
    }

    return true;
}

// Check completion status
$is_profile_complete = isProfileComplete($detail, $mobile_value);
$is_verified = $detail['is_verified'] == 1;

// If profile is complete but not verified, show waiting message instead of form
$show_waiting_message = $is_profile_complete && !$is_verified;

// Calculate completion percentage (keep your existing logic)
$fields_completed = 0;
$total_fields = 7;
$required_fields = ['sex', 'birthplace', 'birthdate', 'occupation', 'id_type'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!empty($detail[$field] ?? '')) {
        $fields_completed++;
    } else {
        $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
    }
}

// Check mobile and government ID
if (!empty($mobile_value)) $fields_completed++;
else $missing_fields[] = 'Mobile';

if (!empty($detail['government_id_path'])) $fields_completed++;
else $missing_fields[] = 'Government ID';

$completion_percentage = ($fields_completed / $total_fields) * 100;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_waiting_message ? 'Verification Pending' : 'Complete Your Profile'; ?> - Step by Step</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if (!$show_waiting_message): ?>
        <!-- Only load reCAPTCHA if form will be shown -->
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>

    <style>
        .step-indicator {
            transition: all 0.3s ease;
        }

        .step-completed {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .step-active {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.4);
        }

        .step-pending {
            background: #f3f4f6;
            color: #6b7280;
            border: 2px solid #e5e7eb;
        }

        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
            animation: slideIn 0.3s ease-in-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .progress-line {
            height: 2px;
            background: #e5e7eb;
            position: relative;
            margin: 0 10px;
            flex: 1;
        }

        .progress-line.completed {
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .file-drop-area {
            border: 2px dashed #d1d5db;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }

        .file-drop-area.dragover {
            border-color: #f59e0b;
            background-color: #fffbeb;
            transform: scale(1.02);
        }

        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="container mx-auto px-4 max-w-5xl py-8">

        <div class="container mx-auto px-4 max-w-5xl py-8">

            <!-- Show notifications first -->
            <?php if ($notification): ?>
                <div class="mb-6">
                    <?php echo $notification; ?>
                </div>
            <?php endif; ?>

            <?php if ($show_waiting_message): ?>
                <!-- Verification Pending Message -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-orange-400 rounded-full mb-6">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h1 class="text-4xl font-bold text-gray-800 mb-4">Profile Submitted Successfully!</h1>
                    <p class="text-xl text-gray-600 mb-8">Your profile is complete and awaiting admin verification</p>
                </div>

                <!-- Status Card -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden max-w-2xl mx-auto">
                    <!-- Header -->
                    <div class="bg-orange-400 px-8 py-6 text-white">
                        <h2 class="text-2xl font-bold">Verification Status</h2>
                        <p class="text-blue-100">Your account details are being reviewed</p>
                    </div>

                    <!-- Content -->
                    <div class="p-8">
                        <!-- Progress Indicator -->
                        <div class="flex items-center justify-between mb-8">
                            <div class="flex items-center text-green-600">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="font-semibold">Profile Submitted</span>
                            </div>

                            <div class="flex-1 h-1 bg-gray-200 mx-4 rounded">
                                <div class="h-1 bg-blue-500 rounded" style="width: 50%"></div>
                            </div>

                            <div class="flex items-center text-blue-600">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3 animate-pulse">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <span class="font-semibold">Under Review</span>
                            </div>

                            <div class="flex-1 h-1 bg-gray-200 mx-4 rounded"></div>

                            <div class="flex items-center text-gray-400">
                                <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
                                    </svg>
                                </div>
                                <span class="font-semibold">Verified</span>
                            </div>
                        </div>

                        <!-- Profile Summary -->
                        <div class="bg-gray-50 rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Submitted Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Name:</span>
                                    <span class="font-medium"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Email:</span>
                                    <span class="font-medium"><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Gender:</span>
                                    <span class="font-medium"><?= ucfirst(htmlspecialchars($detail['sex'])) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Mobile:</span>
                                    <span class="font-medium">+63<?= htmlspecialchars($mobile_value) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Birthdate:</span>
                                    <span class="font-medium"><?= htmlspecialchars($detail['birthdate']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">ID Type:</span>
                                    <span class="font-medium"><?= htmlspecialchars($id_types[$detail['id_type']] ?? $detail['id_type']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- What happens next -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800">What happens next?</h3>
                            <div class="space-y-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <span class="text-blue-600 text-sm font-bold">1</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800">Admin Review</h4>
                                        <p class="text-gray-600 text-sm">Our team will review your submitted documents and information</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <span class="text-blue-600 text-sm font-bold">2</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800">Verification Decision</h4>
                                        <p class="text-gray-600 text-sm">You'll receive an email notification with the verification result</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <span class="text-blue-600 text-sm font-bold">3</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800">Full Access</h4>
                                        <p class="text-gray-600 text-sm">Once verified, you'll have complete access to all platform features</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3 mt-8">
                            <a href="index.php" class="flex-1 bg-orange-400 text-white py-3 px-6 rounded-lg font-semibold text-center hover:bg-blue-700 transition">
                                Go to Dashboard
                            </a>
                            <button onclick="window.location.reload()" class="bg-gray-200 text-gray-800 py-3 px-6 rounded-lg font-semibold hover:bg-gray-300 transition">
                                Refresh Status
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="mt-8 max-w-2xl mx-auto">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h3 class="font-semibold text-yellow-800 mb-2">Need Help?</h3>
                                <p class="text-yellow-700 text-sm mb-3">
                                    Verification typically takes 1-3 business days. If you have urgent concerns or need to update your information, please contact our support team.
                                </p>
                                <a href="mailto:support@yoursite.com" class="text-yellow-800 hover:text-yellow-900 font-medium text-sm underline">
                                    Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>




                <!-- Header Section -->

                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-orange-400 to-orange-600 rounded-full mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Complete Your Profile</h1>
                    <p class="text-gray-600 text-lg">Follow these simple steps to verify your account</p>

                    <!-- Progress indicator -->
                    <?php if ($completion_percentage < 100): ?>
                        <div class="mt-4 max-w-md mx-auto">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-600">Progress</span>
                                <span class="text-sm font-medium text-orange-600"><?= round($completion_percentage) ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-orange-400 to-orange-600 h-2 rounded-full transition-all duration-300" style="width: <?= $completion_percentage ?>%"></div>
                            </div>
                            <?php if (!empty($missing_fields)): ?>
                                <p class="text-xs text-gray-500 mt-2">Missing: <?= implode(', ', $missing_fields) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-orange-400 to-orange-600 rounded-full mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Complete Your Profile</h1>
                    <p class="text-gray-600 text-lg">Follow these simple steps to verify your account</p>
                </div>

                <!-- Step Indicator -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <!-- Progress Line with Steps -->
                    <div class="relative mb-8">
                        <!-- Background Progress Line -->
                        <div class="absolute top-5 left-0 right-0 h-0.5 bg-gray-200"></div>

                        <!-- Active Progress Line -->
                        <div class="absolute top-5 left-0 h-0.5 bg-gradient-to-r from-orange-400 to-orange-600 transition-all duration-500"
                            id="active-progress-line" style="width: 0%"></div>

                        <!-- Step Circles -->
                        <div class="relative flex justify-between">
                            <div class="flex flex-col items-center">
                                <div class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm step-active z-10 bg-white" id="step-1-indicator">
                                    1
                                </div>
                                <div class="mt-3 text-center">
                                    <h3 class="font-semibold text-sm text-gray-800" id="step-1-title">Personal Info</h3>
                                    <p class="text-xs text-gray-500" id="step-1-desc">Basic details</p>
                                </div>
                            </div>

                            <div class="flex flex-col items-center">
                                <div class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm step-pending z-10 bg-white" id="step-2-indicator">
                                    2
                                </div>
                                <div class="mt-3 text-center">
                                    <h3 class="font-semibold text-sm text-gray-600" id="step-2-title">Contact Info</h3>
                                    <p class="text-xs text-gray-500" id="step-2-desc">Mobile number</p>
                                </div>
                            </div>

                            <div class="flex flex-col items-center">
                                <div class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm step-pending z-10 bg-white" id="step-3-indicator">
                                    3
                                </div>
                                <div class="mt-3 text-center">
                                    <h3 class="font-semibold text-sm text-gray-600" id="step-3-title">ID Verification</h3>
                                    <p class="text-xs text-gray-500" id="step-3-desc">Government ID</p>
                                </div>
                            </div>

                            <div class="flex flex-col items-center">
                                <div class="step-indicator w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm step-pending z-10 bg-white" id="step-4-indicator">
                                    4
                                </div>
                                <div class="mt-3 text-center">
                                    <h3 class="font-semibold text-sm text-gray-600" id="step-4-title">Security Check</h3>
                                    <p class="text-xs text-gray-500" id="step-4-desc">Final verification</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Multi-Step Form -->
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" enctype="multipart/form-data" id="profileForm">

                        <!-- Step 1: Personal Information -->
                        <div class="form-step active" id="step-1">
                            <div class="text-center mb-8">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <h2 class="text-2xl font-bold text-gray-800 mb-2">Personal Information</h2>
                                <p class="text-gray-600">Let's start with your basic details</p>
                            </div>

                            <div class="max-w-md mx-auto space-y-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Gender *</label>
                                    <div class="grid grid-cols-3 gap-3">
                                        <button type="button" class="gender-btn p-4 border-2 border-gray-200 rounded-lg text-center hover:border-orange-400 transition-all duration-200" data-value="male">
                                            <div class="w-8 h-8 mx-auto mb-2 text-blue-500">
                                                <svg fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M9,9C10.29,9 11.5,9.41 12.47,10.11L17.58,5H13V3H21V11H19V6.41L13.89,11.5C14.59,12.5 15,13.7 15,15A6,6 0 0,1 9,21A6,6 0 0,1 3,15A6,6 0 0,1 9,9M9,11A4,4 0 0,0 5,15A4,4 0 0,0 9,19A4,4 0 0,0 13,15A4,4 0 0,0 9,11Z" />
                                                </svg>
                                            </div>
                                            <span class="text-sm font-medium">Male</span>
                                        </button>
                                        <button type="button" class="gender-btn p-4 border-2 border-gray-200 rounded-lg text-center hover:border-orange-400 transition-all duration-200" data-value="female">
                                            <div class="w-8 h-8 mx-auto mb-2 text-pink-500">
                                                <svg fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12,4A6,6 0 0,1 18,10C18,12.97 15.84,15.44 13,15.92V18H15V20H13V22H11V20H9V18H11V15.92C8.16,15.44 6,12.97 6,10A6,6 0 0,1 12,4M12,6A4,4 0 0,0 8,10A4,4 0 0,0 12,14A4,4 0 0,0 16,10A4,4 0 0,0 12,6Z" />
                                                </svg>
                                            </div>
                                            <span class="text-sm font-medium">Female</span>
                                        </button>
                                        <button type="button" class="gender-btn p-4 border-2 border-gray-200 rounded-lg text-center hover:border-orange-400 transition-all duration-200" data-value="other">
                                            <div class="w-8 h-8 mx-auto mb-2 text-purple-500">
                                                <svg fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4M12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8Z" />
                                                </svg>
                                            </div>
                                            <span class="text-sm font-medium">Other</span>
                                        </button>
                                    </div>
                                    <input type="hidden" name="sex" id="sex" required>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Birthdate *</label>
                                    <input type="date" name="birthdate" value="<?= htmlspecialchars($detail['birthdate']) ?>"
                                        max="<?= date('Y-m-d') ?>" required
                                        class="w-full p-4 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Birthplace *</label>
                                    <input type="text" name="birthplace" value="<?= htmlspecialchars($detail['birthplace']) ?>"
                                        placeholder="e.g., Manila, Philippines" required
                                        class="w-full p-4 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Occupation *</label>
                                    <input type="text" name="occupation" value="<?= htmlspecialchars($detail['occupation']) ?>"
                                        placeholder="e.g., Software Developer, Teacher, Student" required
                                        class="w-full p-4 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                                </div>
                            </div>

                            <div class="flex justify-end mt-8">
                                <button type="button" onclick="nextStep(2)" class="btn-primary px-8 py-3 rounded-lg font-semibold text-white">
                                    Continue
                                    <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Contact Information -->
                        <div class="form-step" id="step-2">
                            <div class="text-center mb-8">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-green-400 to-green-600 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <h2 class="text-2xl font-bold text-gray-800 mb-2">Contact Information</h2>
                                <p class="text-gray-600">Add your mobile number for account security</p>
                            </div>

                            <div class="max-w-md mx-auto">
                                <div class="text-center mb-6">
                                    <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                                        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Mobile Number *</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500 font-medium">+63</span>
                                        <input type="tel" name="mobile" value="<?= htmlspecialchars($mobile_value) ?>"
                                            placeholder="9123456789"
                                            pattern="[0-9]{10,11}" maxlength="11" required
                                            oninput="formatMobileNumber(this)"
                                            class="w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200 text-lg">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2">Enter your mobile number (09XXXXXXXXX or 9XXXXXXXXX)</p>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <span class="text-green-600">✓ Valid formats: 09123456789 or 9123456789</span>
                                    </div>
                                </div>

                                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <h4 class="text-sm font-semibold text-blue-800">Why do we need this?</h4>
                                            <p class="text-blue-700 text-sm mt-1">Your mobile number helps us secure your account and send important notifications.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between mt-8">
                                <button type="button" onclick="prevStep(1)" class="btn-secondary px-6 py-3 rounded-lg font-semibold">
                                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                    Back
                                </button>
                                <button type="button" onclick="nextStep(3)" class="btn-primary px-8 py-3 rounded-lg font-semibold text-white">
                                    Continue
                                    <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: ID Verification -->
                        <div class="form-step" id="step-3">
                            <div class="text-center mb-8">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-purple-400 to-purple-600 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <h2 class="text-2xl font-bold text-gray-800 mb-2">ID Verification</h2>
                                <p class="text-gray-600">Upload your government-issued ID for verification</p>
                            </div>

                            <div class="max-w-lg mx-auto space-y-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Select ID Type *</label>
                                    <select name="id_type" required class="w-full p-4 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200 text-lg">
                                        <option value="">Choose your ID type</option>
                                        <?php foreach ($id_types as $key => $value): ?>
                                            <option value="<?= $key ?>" <?= $detail['id_type'] === $key ? 'selected' : '' ?>>
                                                <?= $value ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                                        Upload Government ID *
                                        <span class="text-xs text-gray-500 font-normal">(JPG, JPEG, PNG only - Max 5MB)</span>
                                    </label>

                                    <div class="file-drop-area p-8 text-center bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:bg-gray-100 transition-all duration-300"
                                        onclick="document.getElementById('government_id').click()">
                                        <div class="flex flex-col items-center">
                                            <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mb-4">
                                                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                            </div>
                                            <p class="text-lg font-semibold text-gray-700 mb-2">
                                                <span class="text-orange-600">Click to upload</span> or drag and drop
                                            </p>
                                            <p class="text-sm text-gray-500 mb-1">JPG, JPEG, PNG only - up to 5MB</p>
                                            <p class="text-xs text-red-500">PDF and GIF files are not allowed</p>
                                        </div>
                                    </div>

                                    <input type="file" id="government_id" name="government_id" accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                        class="hidden" onchange="previewFile(this)" <?= empty($detail['government_id_path']) ? 'required' : '' ?>>

                                    <!-- File Preview -->
                                    <div id="file-preview" class="mt-4 hidden">
                                        <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg">
                                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span id="file-name" class="text-sm font-medium text-green-800 flex-1"></span>
                                            <button type="button" onclick="removeFile()" class="text-red-500 hover:text-red-700">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Current ID Display -->
                                    <?php if (!empty($detail['government_id_path'])): ?>
                                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <span class="text-sm font-medium text-blue-800">Current ID uploaded</span>
                                                </div>
                                                <a href="../../uploads/government_ids/<?= htmlspecialchars($detail['government_id_path']) ?>"
                                                    target="_blank"
                                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium underline">
                                                    View Current ID
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Security Tips -->
                                    <div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                        <h4 class="text-sm font-semibold text-gray-800 mb-2">Security Tips:</h4>
                                        <ul class="text-xs text-gray-600 space-y-1">
                                            <li>• Ensure your ID is clear and readable</li>
                                            <li>• All corners of the ID should be visible</li>
                                            <li>• Avoid glare or shadows on the document</li>
                                            <li>• Your information will be kept secure and private</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between mt-8">
                                <button type="button" onclick="prevStep(2)" class="btn-secondary px-6 py-3 rounded-lg font-semibold">
                                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                    Back
                                </button>
                                <button type="button" onclick="nextStep(4)" class="btn-primary px-8 py-3 rounded-lg font-semibold text-white">
                                    Continue
                                    <svg class="w-5 h-5 inline ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Step 4: Security Verification -->
                        <div class="form-step" id="step-4">
                            <div class="text-center mb-8">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-red-400 to-red-600 rounded-full mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.5-1.5a8.97 8.97 0 00-4.5-1.2c-5 0-9 4-9 9s4 9 9 9 9-4 9-9c0-1.6-.4-3.1-1.1-4.4" />
                                    </svg>
                                </div>
                                <h2 class="text-2xl font-bold text-gray-800 mb-2">Security Verification</h2>
                                <p class="text-gray-600">Complete the security check to finish your profile</p>
                            </div>

                            <div class="max-w-md mx-auto">
                                <!-- Profile Summary -->
                                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6 mb-6 border">
                                    <h3 class="font-semibold text-gray-800 mb-4 text-center">Profile Summary</h3>
                                    <div class="space-y-3 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Name:</span>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Email:</span>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Gender:</span>
                                            <span class="font-medium text-gray-800" id="summary-sex">-</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Mobile:</span>
                                            <span class="font-medium text-gray-800" id="summary-mobile">-</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">ID Type:</span>
                                            <span class="font-medium text-gray-800" id="summary-id-type">-</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- reCAPTCHA Widget -->
                                <div class="mb-6">
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Security Verification *</label>
                                    <div class="p-6 bg-gray-50 rounded-lg border-2 border-gray-200">
                                        <div class="g-recaptcha mx-auto" data-sitekey="6LeYkKkrAAAAADQDCnPgE96F0kx8XE4Co9apl91U"></div>
                                        <div id="recaptcha-error" class="text-red-600 text-sm mt-3 hidden">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Please complete the reCAPTCHA verification to continue.
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2 text-center">Please verify that you are not a robot</p>
                                </div>

                                <!-- Final Notice -->
                                <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg mb-6">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-orange-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <h4 class="text-sm font-semibold text-orange-800">Almost done!</h4>
                                            <p class="text-orange-700 text-sm mt-1">
                                                Your profile will be submitted for admin verification. You'll receive a notification once approved.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between mt-8">
                                <button type="button" onclick="prevStep(3)" class="btn-secondary px-6 py-3 rounded-lg font-semibold">
                                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                    Back
                                </button>
                                <button type="submit" id="submitButton" class="btn-primary px-8 py-3 rounded-lg font-semibold text-white">
                                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span id="submitText">Complete Profile</span>
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            <?php endif; ?>
            <!-- Bottom Info Card -->
            <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Your Information is Safe</h3>
                        <p class="text-sm text-gray-600">We use industry-standard encryption to protect your personal data and government ID.</p>
                    </div>
                </div>
            </div>

        </div>

        <script>
            let currentStep = 1;
            const totalSteps = 4;

            // Initialize step indicators based on existing data
            document.addEventListener('DOMContentLoaded', function() {
                // Check which fields are already completed
                const existingData = {
                    sex: '<?= htmlspecialchars($detail['sex']) ?>',
                    birthdate: '<?= htmlspecialchars($detail['birthdate']) ?>',
                    birthplace: '<?= htmlspecialchars($detail['birthplace']) ?>',
                    occupation: '<?= htmlspecialchars($detail['occupation']) ?>',
                    mobile: '<?= htmlspecialchars($mobile_value) ?>',
                    id_type: '<?= htmlspecialchars($detail['id_type']) ?>',
                    government_id: '<?= !empty($detail['government_id_path']) ? 'uploaded' : '' ?>'
                };

                // Set gender buttons if sex is already selected
                if (existingData.sex) {
                    const genderBtn = document.querySelector(`[data-value="${existingData.sex}"]`);
                    if (genderBtn) {
                        selectGender(existingData.sex);
                    }
                }

                // Setup mobile input event listeners - TARGET THE CORRECT FIELD
                const mobileInput = getMobileInput();
                if (mobileInput) {
                    console.log('Found mobile input:', mobileInput);

                    // Add multiple event listeners to capture value changes
                    mobileInput.addEventListener('input', function() {
                        formatMobileNumber(this);
                        console.log('Mobile input event - value:', this.value);
                    });

                    mobileInput.addEventListener('blur', function() {
                        console.log('Mobile input blur event, current value:', this.value);
                    });

                    mobileInput.addEventListener('change', function() {
                        console.log('Mobile input change event, current value:', this.value);
                    });

                    // Store initial value
                    mobileInput.setAttribute('data-current-value', mobileInput.value);
                } else {
                    console.error('Mobile input not found during initialization!');
                }

                // Reset reCAPTCHA
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

            // CRITICAL FIX: Function to get the correct mobile input field
            function getMobileInput() {
                // First try to get the mobile input within step-2
                const step2 = document.getElementById('step-2');
                if (step2) {
                    const mobileInStep2 = step2.querySelector('input[name="mobile"]');
                    if (mobileInStep2) {
                        return mobileInStep2;
                    }
                }

                // Fallback: get all mobile inputs and find the one that's in the form
                const allMobileInputs = document.querySelectorAll('input[name="mobile"]');
                const profileForm = document.getElementById('profileForm');

                for (let input of allMobileInputs) {
                    if (profileForm.contains(input)) {
                        return input;
                    }
                }

                // Last resort: just get the first one
                return allMobileInputs.length > 0 ? allMobileInputs[0] : null;
            }

            // Gender selection functionality
            document.querySelectorAll('.gender-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    selectGender(value);
                });
            });

            function selectGender(value) {
                // Remove previous selections
                document.querySelectorAll('.gender-btn').forEach(btn => {
                    btn.classList.remove('border-orange-400', 'bg-orange-50');
                    btn.classList.add('border-gray-200');
                });

                // Select current button
                const selectedBtn = document.querySelector(`[data-value="${value}"]`);
                if (selectedBtn) {
                    selectedBtn.classList.remove('border-gray-200');
                    selectedBtn.classList.add('border-orange-400', 'bg-orange-50');
                }

                // Set hidden input value
                document.getElementById('sex').value = value;
            }

            // Step navigation functions
            function nextStep(step) {
                // Add debug for step 2 specifically
                if (currentStep === 2) {
                    debugMobileField();
                }

                if (validateCurrentStep()) {
                    updateStepIndicator(currentStep, 'completed');
                    currentStep = step;
                    updateStepIndicator(currentStep, 'active');
                    showStep(step);
                    updateSummary();
                }
            }

            function prevStep(step) {
                updateStepIndicator(currentStep, 'pending');
                currentStep = step;
                updateStepIndicator(currentStep, 'active');
                showStep(step);
            }

            function showStep(step) {
                // Hide all steps
                document.querySelectorAll('.form-step').forEach(stepDiv => {
                    stepDiv.classList.remove('active');
                });

                // Show current step
                document.getElementById(`step-${step}`).classList.add('active');

                // Scroll to top
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            function updateStepIndicator(step, status) {
                const indicator = document.getElementById(`step-${step}-indicator`);
                const title = document.getElementById(`step-${step}-title`);

                // Remove all status classes
                indicator.classList.remove('step-completed', 'step-active', 'step-pending');

                // Add appropriate status class
                indicator.classList.add(`step-${status}`);

                // Update progress lines
                if (status === 'completed' && step < totalSteps) {
                    const progressLine = document.getElementById(`progress-${step}-${step + 1}`);
                    if (progressLine) {
                        progressLine.classList.add('completed');
                    }
                }

                // Update title colors
                if (status === 'active' || status === 'completed') {
                    title.classList.remove('text-gray-600');
                    title.classList.add('text-gray-800');
                } else {
                    title.classList.remove('text-gray-800');
                    title.classList.add('text-gray-600');
                }

                // Add checkmark for completed steps
                if (status === 'completed') {
                    indicator.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
                } else {
                    indicator.textContent = step;
                }
            }

            // COMPLETELY REWRITTEN VALIDATION FUNCTION
            function validateCurrentStep() {
                switch (currentStep) {
                    case 1:
                        const sex = document.getElementById('sex').value;
                        const birthdate = document.querySelector('input[name="birthdate"]').value;
                        const birthplace = document.querySelector('input[name="birthplace"]').value;
                        const occupation = document.querySelector('input[name="occupation"]').value;

                        if (!sex || !birthdate || !birthplace || !occupation) {
                            alert('Please fill in all required fields before continuing.');
                            return false;
                        }
                        return true;

                    case 2:
                        // Use the correct mobile input getter
                        const mobileInput = getMobileInput();

                        if (!mobileInput) {
                            console.error('Mobile input field not found!');
                            alert('Mobile input field not found. Please refresh the page.');
                            return false;
                        }

                        // Get the current value from the correct input field
                        const mobile = mobileInput.value.trim();

                        console.log('Raw mobile input during validation:', mobile);
                        console.log('Mobile input element being validated:', mobileInput);

                        if (!mobile || mobile === '') {
                            alert('Please enter your mobile number.');
                            mobileInput.focus();
                            return false;
                        }

                        // Clean the mobile number (remove non-digits)
                        const cleanMobile = mobile.replace(/\D/g, '');
                        console.log('Cleaned mobile during validation:', cleanMobile);

                        // Handle leading zero (convert 09 to 9)
                        const finalMobile = cleanMobile.startsWith('0') ? cleanMobile.substring(1) : cleanMobile;
                        console.log('Final mobile during validation:', finalMobile);

                        // Check length (should be 10 digits after cleaning)
                        if (finalMobile.length !== 10) {
                            alert(`Please enter a valid Philippine mobile number (10 digits). You entered ${finalMobile.length} digits.`);
                            mobileInput.focus();
                            return false;
                        }

                        // Must start with 9
                        if (!finalMobile.startsWith('9')) {
                            alert('Philippine mobile numbers should start with 9.');
                            mobileInput.focus();
                            return false;
                        }

                        // CRITICAL: Update the input value with cleaned version for submission
                        mobileInput.value = finalMobile;
                        mobileInput.setAttribute('data-current-value', finalMobile);
                        console.log('Mobile value set for submission:', finalMobile);
                        return true;

                    case 3:
                        const idType = document.querySelector('select[name="id_type"]').value;
                        const governmentId = document.getElementById('government_id').files.length;
                        const existingId = '<?= !empty($detail['government_id_path']) ? 'exists' : '' ?>';

                        if (!idType) {
                            alert('Please select your ID type.');
                            return false;
                        }

                        if (governmentId === 0 && !existingId) {
                            alert('Please upload your government ID.');
                            return false;
                        }
                        return true;

                    default:
                        return true;
                }
            }

            function updateSummary() {
                // Update summary in step 4 - use the correct mobile input
                const sexValue = document.getElementById('sex').value;
                const mobileInput = getMobileInput();
                const mobileValue = mobileInput ? mobileInput.value : '';
                const idTypeValue = document.querySelector('select[name="id_type"]').value;

                document.getElementById('summary-sex').textContent = sexValue ? sexValue.charAt(0).toUpperCase() + sexValue.slice(1) : '-';
                document.getElementById('summary-mobile').textContent = mobileValue ? '+63' + mobileValue : '-';

                // Get ID type display name
                const idTypeOptions = document.querySelector('select[name="id_type"]').options;
                let idTypeText = '-';
                for (let option of idTypeOptions) {
                    if (option.value === idTypeValue) {
                        idTypeText = option.textContent;
                        break;
                    }
                }
                document.getElementById('summary-id-type').textContent = idTypeText;
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

                // Final mobile validation before submission
                const mobileInput = getMobileInput();
                if (mobileInput && mobileInput.value) {
                    console.log('Final mobile value before submission:', mobileInput.value);
                }

                // Show loading state
                submitButton.disabled = true;
                submitText.textContent = 'Submitting...';

                // Add loading spinner
                submitButton.innerHTML = `
            <svg class="animate-spin w-5 h-5 inline mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Submitting Profile...
        `;

                return true;
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

            if (dropArea && fileInput) {
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
            }

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

            // FIXED Mobile number formatting function
            function formatMobileNumber(input) {
                let value = input.value.replace(/\D/g, ''); // Remove non-digits

                console.log('Format input:', value);

                // Handle different input formats
                if (value.startsWith('63')) {
                    // Remove country code if user types it
                    value = value.substring(2);
                }

                // Limit to 11 digits to allow 09XXXXXXXXX format
                if (value.length > 11) {
                    value = value.slice(0, 11);
                }

                input.value = value;
                console.log('Formatted value:', value);

                // Store the current value in a data attribute for validation
                input.setAttribute('data-current-value', value);
            }

            // Enhanced debug function for mobile field
            function debugMobileField() {
                console.log('=== ENHANCED MOBILE DEBUG ===');

                // Check all mobile inputs
                const allMobileInputs = document.querySelectorAll('input[name="mobile"]');
                console.log('Total mobile inputs found:', allMobileInputs.length);

                allMobileInputs.forEach((input, index) => {
                    console.log(`Mobile input ${index + 1}:`, input);
                    console.log(`  - Value: "${input.value}"`);
                    console.log(`  - ID: "${input.id}"`);
                    console.log(`  - Parent element:`, input.parentElement);
                    console.log(`  - Is in form:`, document.getElementById('profileForm').contains(input));
                });

                // Check the one we're targeting
                const targetMobileInput = getMobileInput();
                console.log('Target mobile input:', targetMobileInput);
                console.log('Target mobile value:', targetMobileInput ? targetMobileInput.value : 'NOT FOUND');

                console.log('Current step:', currentStep);
                console.log('===============================');
            }

            // COMPLETELY REWRITTEN VALIDATION FUNCTION
            function validateCurrentStep() {
                switch (currentStep) {
                    case 1:
                        const sex = document.getElementById('sex').value;
                        const birthdate = document.querySelector('input[name="birthdate"]').value;
                        const birthplace = document.querySelector('input[name="birthplace"]').value;
                        const occupation = document.querySelector('input[name="occupation"]').value;

                        if (!sex || !birthdate || !birthplace || !occupation) {
                            alert('Please fill in all required fields before continuing.');
                            return false;
                        }
                        return true;

                    case 2:
                        // Use the correct mobile input getter
                        const mobileInput = getMobileInput();

                        if (!mobileInput) {
                            console.error('Mobile input field not found!');
                            alert('Mobile input field not found. Please refresh the page.');
                            return false;
                        }

                        // Get the current value from the correct input field
                        const mobile = mobileInput.value.trim();

                        console.log('Raw mobile input during validation:', mobile);
                        console.log('Mobile input element being validated:', mobileInput);

                        if (!mobile || mobile === '') {
                            alert('Please enter your mobile number.');

                            // Make sure the step is visible before focusing
                            showStep(2);
                            setTimeout(() => mobileInput.focus(), 100);
                            return false;
                        }

                        // Clean the mobile number (remove non-digits)
                        const cleanMobile = mobile.replace(/\D/g, '');
                        console.log('Cleaned mobile during validation:', cleanMobile);

                        // Handle leading zero (convert 09 to 9)
                        const finalMobile = cleanMobile.startsWith('0') ? cleanMobile.substring(1) : cleanMobile;
                        console.log('Final mobile during validation:', finalMobile);

                        // Check length (should be 10 digits after cleaning)
                        if (finalMobile.length !== 10) {
                            alert(`Please enter a valid Philippine mobile number (10 digits). You entered ${finalMobile.length} digits.`);
                            showStep(2);
                            setTimeout(() => mobileInput.focus(), 100);
                            return false;
                        }

                        // Must start with 9
                        if (!finalMobile.startsWith('9')) {
                            alert('Philippine mobile numbers should start with 9.');
                            showStep(2);
                            setTimeout(() => mobileInput.focus(), 100);
                            return false;
                        }

                        // CRITICAL: Update the input value with cleaned version for submission
                        mobileInput.value = finalMobile;
                        mobileInput.setAttribute('data-current-value', finalMobile);
                        console.log('Mobile value set for submission:', finalMobile);
                        return true;

                    case 3:
                        const idType = document.querySelector('select[name="id_type"]').value;
                        const governmentId = document.getElementById('government_id').files.length;
                        const existingId = '<?= !empty($detail['government_id_path']) ? 'exists' : '' ?>';

                        if (!idType) {
                            alert('Please select your ID type.');
                            return false;
                        }

                        if (governmentId === 0 && !existingId) {
                            alert('Please upload your government ID.');
                            return false;
                        }
                        return true;

                    default:
                        return true;
                }
            }

            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();

                    if (currentStep < totalSteps) {
                        if (validateCurrentStep()) {
                            nextStep(currentStep + 1);
                        }
                    } else {
                        // On last step, submit form
                        document.getElementById('profileForm').submit();
                    }
                }
            });

            // Show notifications if any
            <?php if ($notification): ?>
                // Show notification
                const notificationHtml = `<?= addslashes($notification) ?>`;
                const notificationDiv = document.createElement('div');
                notificationDiv.innerHTML = notificationHtml;
                notificationDiv.className = 'fixed top-4 right-4 z-50 max-w-md';
                document.body.appendChild(notificationDiv);

                // Auto-hide after 5 seconds
                setTimeout(() => {
                    notificationDiv.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    notificationDiv.style.opacity = '0';
                    notificationDiv.style.transform = 'translateX(100%)';
                    setTimeout(() => notificationDiv.remove(), 500);
                }, 5000);

                // Scroll to top to show notification
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            <?php endif; ?>
        </script>

</body>

</html>