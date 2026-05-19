<?php
// verification-pending.php
include ROOT_PATH . "/connection/connect.php";

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/googlecallback');
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
    header('Location: ' . BASE_URL . '/');
    exit;
}

// Get submission date
$submission_date = date('Y-m-d H:i:s');

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
    
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <style>
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse-ring {
            0% { transform: scale(1); opacity: 0.6; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        .animate-spin-slow { animation: spin-slow 8s linear infinite; }
        .animate-fade-up { animation: fade-up 0.6s ease both; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .pulse-ring {
            animation: pulse-ring 2s ease-out infinite;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen font-sans antialiased">


    <div class="min-h-screen flex items-center justify-center px-4 py-16">
        <div class="w-full max-w-lg">

            <!-- Card -->
            <div class="bg-white rounded-3xl shadow-xl overflow-hidden animate-fade-up">

                <!-- Top accent bar -->
                <div class="h-1.5 w-full bg-gradient-to-r from-blue-400 via-blue-600 to-indigo-600"></div>

                <div class="px-8 pt-10 pb-8">

                    <!-- Icon with pulse rings -->
                    <div class="flex justify-center mb-8 animate-fade-up delay-1">
                        <div class="relative flex items-center justify-center">
                            <!-- Outer pulse ring -->
                            <span class="absolute w-24 h-24 rounded-full bg-blue-100 pulse-ring"></span>
                            <!-- Static ring -->
                            <span class="absolute w-20 h-20 rounded-full bg-blue-50 border-2 border-blue-100"></span>
                            <!-- Icon circle -->
                            <div class="relative w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-200">
                                <svg class="w-8 h-8 text-white animate-spin-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Heading -->
                    <div class="text-center mb-8 animate-fade-up delay-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold tracking-wide uppercase mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span>
                            Under Review
                        </span>
                        <h1 class="text-2xl font-bold text-slate-900 mb-2 tracking-tight">Account Verification Pending</h1>
                        <p class="text-slate-500 text-sm leading-relaxed">
                            Your profile is currently being reviewed. Our team will verify your information within
                            <span class="font-semibold text-blue-600">24 hours</span>.
                        </p>
                    </div>

                    <!-- Info Grid -->
                    <div class="grid grid-cols-2 gap-3 mb-6 animate-fade-up delay-3">
                        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Submitted</p>
                            <p class="text-sm font-bold text-slate-800"><?php echo date('M d, Y', strtotime($submission_date)); ?></p>
                            <p class="text-xs text-slate-500"><?php echo date('h:i A', strtotime($submission_date)); ?></p>
                        </div>
                        <div class="bg-blue-50 rounded-2xl p-4 border border-blue-100">
                            <p class="text-xs font-semibold text-blue-400 uppercase tracking-wide mb-1">Expected By</p>
                            <p class="text-sm font-bold text-blue-900"><?php echo date('M d, Y', strtotime($submission_date . ' +24 hours')); ?></p>
                            <p class="text-xs text-blue-500"><?php echo date('h:i A', strtotime($submission_date . ' +24 hours')); ?></p>
                        </div>
                    </div>

                    <!-- Email row -->
                    <div class="flex items-center gap-3 bg-slate-50 rounded-2xl px-4 py-3 border border-slate-100 mb-6 animate-fade-up delay-3">
                        <div class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Account Email</p>
                            <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>

                    <!-- Steps -->
                    <div class="mb-6 animate-fade-up delay-4">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">What happens next</p>
                        <div class="space-y-3">
                            <?php
                            $steps = [
                                ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'text' => 'Our team reviews your submitted documents and information'],
                                ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'text' => 'We verify your identity and validate all provided details'],
                                ['icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'text' => "You'll receive an email notification once your account is approved"],
                            ];
                            foreach ($steps as $i => $step): ?>
                            <div class="flex items-start gap-3">
                                <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center shrink-0 text-xs font-bold mt-0.5">
                                    <?= $i + 1 ?>
                                </div>
                                <div class="flex-1 bg-slate-50 rounded-xl px-4 py-3 border border-slate-100">
                                    <p class="text-sm text-slate-600"><?= $step['text'] ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tip -->
                    <div class="flex gap-3 bg-amber-50 border border-amber-100 rounded-2xl px-4 py-3 mb-8 animate-fade-up delay-4">
                        <span class="text-amber-500 text-lg leading-none mt-0.5">💡</span>
                        <p class="text-xs text-amber-700 leading-relaxed">
                            <span class="font-bold">Helpful tip:</span> Keep your email and phone number active. We may contact you for additional verification if needed.
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col gap-3 animate-fade-up delay-4">
                        <a href="<?= BASE_URL ?>/"
                           class="w-full py-3 px-6 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold text-sm rounded-xl text-center transition-colors duration-150 shadow-sm shadow-blue-200">
                            Go to Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>/profile"
                           class="w-full py-3 px-6 bg-white hover:bg-slate-50 active:bg-slate-100 text-slate-700 font-semibold text-sm rounded-xl text-center border border-slate-200 transition-colors duration-150">
                            Edit Profile
                        </a>
                    </div>

                </div>

                <!-- Footer strip -->
                <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 text-center">
                    <p class="text-xs text-slate-400">
                        Need help?
                        <a href="mailto:support@example.com" class="text-blue-600 font-semibold hover:underline ml-1">Contact Support</a>
                    </p>
                </div>

            </div>
        </div>
    </div>

    <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>

</body>
</html>