<?php
// verification-pending.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Check verification status
$verify_stmt = $conn->prepare("SELECT is_verified FROM user_details WHERE user_id = ?");
$verify_stmt->bind_param("i", $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();
$verify_data = $verify_result->fetch_assoc();
$verify_stmt->close();

$is_verified = $verify_data['is_verified'] ?? 0;

// If already verified, redirect to dashboard
if ($is_verified == 1) {
    header('Location: index-page-1-A-B-C-D-E.php');
    exit;
}

// Get submission date - use current time if no created_at column
$submission_date = date('Y-m-d H:i:s');

// Try to get created_at if the column exists (optional)
try {
    $detail_stmt = $conn->prepare("SELECT created_at FROM user_details WHERE user_id = ? LIMIT 1");
    if ($detail_stmt) {
        $detail_stmt->bind_param("i", $user_id);
        $detail_stmt->execute();
        $detail_result = $detail_stmt->get_result();
        if ($detail_result->num_rows > 0) {
            $detail = $detail_result->fetch_assoc();
            $submission_date = $detail['created_at'];
        }
        $detail_stmt->close();
    }
} catch (Exception $e) {
    // Column doesn't exist, use current date
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Pending</title>
</head>
<body style="font-family: 'Montserrat', sans-serif;" class="bg-white min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full">
            <!-- Main Card -->
            <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
                
                <!-- Pending Icon -->
                <div class="mb-6">
                    <div class="w-24 h-24 mx-auto bg-blue-100 rounded-full flex items-center justify-center animate-pulse">
                        <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Title -->
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    Account Under Review
                </h1>

                <!-- Status Message -->
                <p class="text-gray-600 mb-8 text-lg">
                    Your profile is being verified. Please wait up to <strong class="text-blue-600">24 hours</strong> for our team to review your information.
                </p>

                <!-- Info Box -->
                <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-lg mb-8 text-left">
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-gray-600 font-semibold uppercase">Submission Date</p>
                            <p class="text-gray-900 font-semibold"><?php echo date('M d, Y h:i A', strtotime($submission_date)); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 font-semibold uppercase">Expected Completion</p>
                            <p class="text-gray-900 font-semibold"><?php echo date('M d, Y h:i A', strtotime($submission_date . ' + 24 hours')); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 font-semibold uppercase">Account Email</p>
                            <p class="text-gray-900 font-semibold"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- What's Next -->
                <div class="bg-gray-50 p-6 rounded-lg mb-8 text-left">
                    <h3 class="font-bold text-gray-900 mb-4 flex items-center">
                        <span class="w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm mr-2">📋</span>
                        What happens next?
                    </h3>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-start">
                            <span class="text-blue-600 font-bold mr-3">1.</span>
                            <span>Our team reviews your submitted documents and information</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-600 font-bold mr-3">2.</span>
                            <span>We verify your identity and validate all details</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-600 font-bold mr-3">3.</span>
                            <span>You'll receive an email notification once approved</span>
                        </li>
                    </ul>
                </div>

                <!-- Tips -->
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg mb-8 text-left">
                    <p class="text-xs font-bold text-yellow-900 mb-2">💡 HELPFUL TIP</p>
                    <p class="text-sm text-yellow-800">Keep your email and phone number active. We may contact you for additional verification if needed.</p>
                </div>

                <!-- Buttons -->
                <div class="space-y-3">
                    <a href="index-page-1-A-B-C-D-E.php" class="block w-full px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                        Go to Dashboard
                    </a>
                    <a href="complete-profile.php" class="block w-full px-6 py-3 border-2 border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition">
                        Edit Profile
                    </a>
                </div>

                <!-- Status Badge -->
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <div class="inline-block px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full text-sm font-semibold">
                        ⏳ Status: Under Review
                    </div>
                </div>

            </div>

            <!-- Footer Note -->
            <div class="mt-8 text-center text-sm text-gray-600">
                <p>Questions? <a href="mailto:support@example.com" class="text-blue-600 font-semibold hover:underline">Contact support</a></p>
            </div>
        </div>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <!-- Animations -->
    <style>
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</body>
</html>