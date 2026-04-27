<?php
// complete-profile.php
session_name("nobleuser");
session_start();
include ROOT_PATH . "/connection/connect.php";

// Restore session from remember_token if needed
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
    }
    $stmt->close();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/googlecallback');
    exit;
}

$user_id = $_SESSION['user_id'];

// ✅ Check if user has COMPLETED their profile
$profile_check = $conn->prepare("
    SELECT 
        detail_id,
        sex, 
        birthplace, 
        birthdate, 
        occupation, 
        id_type, 
        government_id_path,
        is_verified
    FROM user_details 
    WHERE user_id = ?
");
$profile_check->bind_param("i", $user_id);
$profile_check->execute();
$profile_result = $profile_check->get_result();

if ($profile_result->num_rows > 0) {
    $profile_data = $profile_result->fetch_assoc();

    // Check kung complete lahat ng required fields
    $is_complete = !empty($profile_data['sex']) &&
        !empty($profile_data['birthplace']) &&
        !empty($profile_data['birthdate']) &&
        !empty($profile_data['occupation']) &&
        !empty($profile_data['id_type']) &&
        !empty($profile_data['government_id_path']);

    // Kung complete, redirect based sa verification status
    if ($is_complete) {
        if ($profile_data['is_verified'] == 1) {
            // Already verified = go to dashboard
            header('Location: ' . BASE_URL . '/');
            exit;
        } else {
            // Complete pero under review = go to verification pending
            header('Location: ' . BASE_URL . '/verificationsettings');
            exit;
        }
    }
}

$profile_check->close();

// Fetch existing user details para i-populate ang form
$existing_data = [];
$existing_stmt = $conn->prepare("
    SELECT 
        ud.sex, 
        ud.birthplace, 
        ud.birthdate, 
        ud.occupation, 
        ud.id_type, 
        ud.government_id_path,
        u.mobile
    FROM user_details ud
    LEFT JOIN users u ON ud.user_id = u.id 
    WHERE ud.user_id = ?
");
$existing_stmt->bind_param("i", $user_id);
$existing_stmt->execute();
$existing_result = $existing_stmt->get_result();

if ($existing_result->num_rows > 0) {
    $existing_data = $existing_result->fetch_assoc();
} else {
    // Walang user_details pa, fetch lang mobile from users table
    $mobile_stmt = $conn->prepare("SELECT mobile FROM users WHERE id = ?");
    $mobile_stmt->bind_param("i", $user_id);
    $mobile_stmt->execute();
    $mobile_result = $mobile_stmt->get_result();
    if ($mobile_result->num_rows > 0) {
        $mobile_data = $mobile_result->fetch_assoc();
        $existing_data['mobile'] = $mobile_data['mobile'];
    }
    $mobile_stmt->close();
}
$existing_stmt->close();

$error = ''; // Initialize error variable

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];

        // ✅ 1. reCAPTCHA VALIDATION FIRST
    if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
        $error = "Please verify that you are not a robot.";
    } else {
        $secretKey = "6LfJalcsAAAAADabQF4nGitXvLn0rnQNWKE8rj9D";
        $captchaResponse = $_POST['g-recaptcha-response'];

        $verifyResponse = file_get_contents(
            "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$captchaResponse}"
        );

        $responseData = json_decode($verifyResponse);

        if (!$responseData->success) {
            $error = "reCAPTCHA verification failed. Please try again.";
        }
    }

    // ✅ 2. GET FORM DATA (kung walang reCAPTCHA error)
    if (empty($error)) {
        $sex = isset($_POST['sex']) ? trim($_POST['sex']) : '';
        $birthplace = isset($_POST['birthplace']) ? trim($_POST['birthplace']) : '';
        $birthdate = isset($_POST['birthdate']) ? trim($_POST['birthdate']) : '';
        $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
        $occupation = isset($_POST['occupation']) ? trim($_POST['occupation']) : '';
        $id_type = isset($_POST['id_type']) ? trim($_POST['id_type']) : '';

        // ✅ 3. VALIDATE FORM FIELDS
        if (empty($sex) || empty($birthplace) || empty($birthdate) || empty($mobile) || empty($occupation) || empty($id_type)) {
            $error = "All fields are required.";
        }

        // Validate gender
        if (empty($error) && !in_array($sex, ['Male', 'Female', 'Other'])) {
            $error = "Invalid gender selection.";
        }

        // Validate birthplace
        if (empty($error) && (strlen($birthplace) < 2 || strlen($birthplace) > 100)) {
            $error = "Birthplace must be between 2 and 100 characters.";
        }

        // Validate date format and age
        if (empty($error)) {
            $date = DateTime::createFromFormat('Y-m-d', $birthdate);
            if (!$date || $date->format('Y-m-d') !== $birthdate) {
                $error = "Invalid date format.";
            } elseif ($date > new DateTime()) {
                $error = "Date of birth cannot be in the future.";
            } else {
                $age = (new DateTime())->diff($date)->y;
                if ($age < 18) {
                    $error = "You must be at least 18 years old.";
                }
            }
        }

        // Validate mobile number
        if (empty($error)) {
            $mobile_digits = preg_replace('/[^0-9]/', '', $mobile);

            // If may bagong mobile number na ineenter
            if (!empty($mobile_digits)) {
                if (strlen($mobile_digits) < 10 || strlen($mobile_digits) > 15) {
                    $error = "Mobile number must be between 10-15 digits.";
                } elseif (!preg_match('/^(9\d{9}|639\d{9}|09\d{9})$/', $mobile_digits)) {
                    $error = "Please enter a valid Philippine mobile number (09XXXXXXXXX or 639XXXXXXXXX).";
                }
            } elseif (empty($existing_data['mobile'])) {
                // Walang existing mobile at walang bagong input
                $error = "Mobile number is required.";
            }
            // Else: may existing mobile at walang bagong input, OK lang
        }

        // Validate occupation
        if (empty($error) && (strlen($occupation) < 2 || strlen($occupation) > 50)) {
            $error = "Occupation must be between 2 and 50 characters.";
        }

        // Validate ID type
        if (empty($error)) {
            $valid_id_types = ['National ID', "Driver's License", 'Passport', 'UMID', 'Senior Citizen ID', 'PWD ID', 'TIN ID'];
            if (!in_array($id_type, $valid_id_types)) {
                $error = "Invalid ID type selected.";
            }
        }

        // ✅ 4. FILE UPLOAD VALIDATION
        if (empty($error)) {
            // If may existing file at walang bagong upload, i-keep lang ang old file
            if (isset($_FILES['government_id']) && $_FILES['government_id']['error'] === UPLOAD_ERR_OK) {
                // May bagong file, process ito
                $file = $_FILES['government_id'];
                $file_name = $file['name'];
                $file_tmp = $file['tmp_name'];
                $file_size = $file['size'];

                // Validate file extension
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (!in_array($file_ext, $allowed_ext)) {
                    $error = "Invalid file type. Only JPG, PNG are allowed.";
                }

                // Validate file size (10MB max)
                if (empty($error) && $file_size > 10 * 1024 * 1024) {
                    $error = "File size exceeds 10MB limit.";
                }

                // Validate file MIME type
                if (empty($error)) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $file_tmp);
                    finfo_close($finfo);

                    $allowed_mimes = ['image/jpeg', 'image/png'];
                    if (!in_array($mime_type, $allowed_mimes)) {
                        $error = "File is not a valid image.";
                    }
                }

                // Upload file
                if (empty($error)) {
                    $upload_dir = '../../uploads/government_ids/';
                    if (!is_dir($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            $error = "Failed to create upload directory.";
                        }
                    }

                    if (empty($error)) {
                        $new_filename = 'gov_id_' . $user_id . '_' . time() . '.' . $file_ext;
                        $upload_path = $upload_dir . $new_filename;

                        if (!move_uploaded_file($file_tmp, $upload_path)) {
                            $error = "Failed to upload file.";
                        } else {
                            $government_id_path = $new_filename;
                        }
                    }
                }
            } elseif ($_FILES['government_id']['error'] !== UPLOAD_ERR_NO_FILE) {
                // May error sa file upload
                $error = "Government ID image is required.";
            } else {
                // Walang bagong file, i-check kung may existing
                if (empty($existing_data['government_id_path'])) {
                    $error = "Government ID image is required.";
                } else {
                    // Keep existing file
                    $government_id_path = $existing_data['government_id_path'];
                }
            }
        }

        // ✅ 5. DATABASE OPERATIONS (kung walang error)
        if (empty($error)) {
            // Check if user already has details
            $check_stmt = $conn->prepare("SELECT detail_id FROM user_details WHERE user_id = ?");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                // Update existing record
                $update_stmt = $conn->prepare("
                    UPDATE user_details 
                    SET sex = ?, 
                        birthplace = ?, 
                        birthdate = ?, 
                        occupation = ?, 
                        id_type = ?, 
                        government_id_path = ?
                    WHERE user_id = ?
                ");
                $update_stmt->bind_param("ssssssi", $sex, $birthplace, $birthdate, $occupation, $id_type, $government_id_path, $user_id);

                if (!$update_stmt->execute()) {
                    $error = "Failed to update profile: " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                // Insert new record
                $insert_stmt = $conn->prepare("
                    INSERT INTO user_details (user_id, sex, birthplace, birthdate, occupation, id_type, government_id_path, is_verified)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $insert_stmt->bind_param("issssss", $user_id, $sex, $birthplace, $birthdate, $occupation, $id_type, $government_id_path);

                if (!$insert_stmt->execute()) {
                    $error = "Failed to save profile: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            }

            $check_stmt->close();

            // Update mobile number in users table (kung may bagong value lang)
            if (!empty($mobile)) {
                $update_users = $conn->prepare("UPDATE users SET mobile = ? WHERE id = ?");
                $update_users->bind_param("si", $mobile, $user_id);
                if (!$update_users->execute()) {
                    $error = "Failed to update mobile number.";
                }
                $update_users->close();
            }

            // Success - redirect
            if (empty($error)) {
                header('Location: ' . BASE_URL . '/verifcationsettings');
                exit;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile</title>
   
</head>

<body style="font-family: 'Montserrat', sans-serif;">
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
  <!-- Breadcrumb -->
  <nav class="bg-white border-b border-gray-200 px-4 py-3">
    <div class="container mx-auto">
      <div class="flex items-center space-x-2 text-sm" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
        <a href=" <?= BASE_URL ?>/profile" class=" hover:text-orange-700 transition duration-200 flex items-center">
          <i class="fas fa-home mr-1"></i>Back
        </a>
        <i class="fas fa-chevron-right text-gray-400"></i>
        <span class=" font-medium">Upgrade to verification</span>
      </div>
    </div>
  </nav>
  
    <div class="min-h-screen py-12 px-4">
        <div class="max-w-2xl mx-auto">
            <div class="p-8">
                <h2 class="text-3xl font-bold mb-2">Upgrade your Profile</h2>
                <p class="text-gray-600 mb-8">Fill in your information to complete registration</p>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="space-y-6">
                        <!-- Gender -->
                        <div>
                            <label class=" text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                               <i class="fa-solid fa-person"></i>
                                Sex
                            </label>
                            <select name="sex" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo isset($_POST['sex']) && $_POST['sex'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo isset($_POST['sex']) && $_POST['sex'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo isset($_POST['sex']) && $_POST['sex'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <!-- Birthplace -->
                        <div>
                            <label class=" text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                              <i class="fa-solid fa-calendar-days"></i>
                                Birthplace
                            </label>
                            <input type="text" name="birthplace" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="e.g., Manila, Philippines" value="<?php echo isset($_POST['birthplace']) ? htmlspecialchars($_POST['birthplace']) : ''; ?>">
                        </div>

                        <!-- Date of Birth -->
                        <div>
                            <label class=" text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                              <i class="fa-solid fa-calendar-days"></i>
                                Date of Birth
                            </label>
                            <input type="date" name="birthdate" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="<?php echo isset($_POST['birthdate']) ? htmlspecialchars($_POST['birthdate']) : ''; ?>">
                        </div>

                        <!-- Occupation -->
                        <div>
                            <label class=" text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                                <i class="fa-solid fa-handshake"></i>
                                Occupation
                            </label>
                            <input type="text" name="occupation" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="e.g., Software Developer" value="<?php echo isset($_POST['occupation']) ? htmlspecialchars($_POST['occupation']) : ''; ?>">
                        </div>

                        <!-- Mobile Number -->
                        <div>
                            <label class=" text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                           <i class="fa-solid fa-phone"></i>
                                Mobile Number
                            </label>
                            <input type="tel" name="mobile" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="09XXXXXXXXX" value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>">
                            <p class="text-sm text-gray-500 mt-2">Format: 09XXXXXXXXX (11 digits)</p>
                        </div>

                        <!-- ID Type -->
                        <div>
                            <label class=" text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                           <i class="fa-solid fa-address-card"></i>
                                ID Type
                            </label>
                            <select name="id_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select ID Type</option>
                                <option value="National ID" <?php echo isset($_POST['id_type']) && $_POST['id_type'] == 'National ID' ? 'selected' : ''; ?>>National ID</option>
                                <option value="Driver's License" <?php echo isset($_POST['id_type']) && $_POST['id_type'] == "Driver's License" ? 'selected' : ''; ?>>Driver's License</option>
                                <option value="Passport" <?php echo isset($_POST['id_type']) && $_POST['id_type'] == 'Passport' ? 'selected' : ''; ?>>Passport</option>
                                <option value="UMID" <?php echo isset($_POST['id_type']) && $_POST['id_type'] == 'UMID' ? 'selected' : ''; ?>>UMID (Unified Multi-Purpose ID)</option>
                                <option value="Senior Citizen ID" <?php echo isset($_POST['id_type']) && $_POST['id_type'] == 'Senior Citizen ID' ? 'selected' : ''; ?>>Senior Citizen ID</option>
                                <option value="PWD ID" <?php echo isset($_POST['id_type']) && $_POST['id_type'] == 'PWD ID' ? 'selected' : ''; ?>>PWD ID</option>
                                <option value="TIN ID" <?php echo isset($_POST['id_type']) && $_POST['id_type'] == 'TIN ID' ? 'selected' : ''; ?>>TIN ID</option>
                            </select>
                        </div>

                        <!-- Government ID Upload -->
                        <div>
                            <label class=" text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                               <i class="fa-solid fa-image"></i>
                                Government ID Photo
                            </label>
                            <input type="file" name="government_id" required accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-sm text-gray-500 mt-2">Upload an image of your valid ID (PNG, JPG up to 10MB)</p>
                        </div>
                    </div>

                    <!-- reCAPTCHA -->
                    <div class="flex justify-center mt-8">
                        <div class="g-recaptcha" data-sitekey="6LfJalcsAAAAAOLu1KUDWyoP1voDlxp_Dsl2Vkn3" data-callback="onReCaptchaSuccess" data-expired-callback="onReCaptchaError"></div>
                    </div>

                    <input type="hidden" id="recaptchaToken" name="g-recaptcha-response">

                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn" class="w-full mt-8 px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                      <i class="fa-solid fa-paper-plane"></i>
                        Submit
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>

    <script>
        // Disable submit button by default
        document.getElementById('submitBtn').disabled = true;

        // Enable button only when reCAPTCHA is verified
        function onReCaptchaSuccess(token) {
            document.getElementById('recaptchaToken').value = token;
            document.getElementById('submitBtn').disabled = false;
        }

        // Reset on error
        function onReCaptchaError() {
            document.getElementById('recaptchaToken').value = '';
            document.getElementById('submitBtn').disabled = true;
        }

        // Form submission validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const recaptchaToken = document.getElementById('recaptchaToken').value;
            if (!recaptchaToken) {
                e.preventDefault();
                alert('Please complete the reCAPTCHA verification');
                return false;
            }
        });
    </script>

</body>

</html>