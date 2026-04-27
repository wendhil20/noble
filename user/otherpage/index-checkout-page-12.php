<?php
// checkout-step1.php - Customer Information Only
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

// Restore session from remember_token
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
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/googlecallback');
    exit;
}

$user_id = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';
$userEmail = '';
$userMobile = '';

// Get user details
if ($user_id) {
    $stmt = $conn->prepare("SELECT email, mobile FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $userEmail = $user_data['email'];
        $userMobile = $user_data['mobile'];
        // Remove leading 0 if present (for display purposes)
        if (!empty($userMobile) && substr($userMobile, 0, 1) === '0') {
            $userMobile = substr($userMobile, 1);
        }
    }
    $stmt->close();
}

// Fetch cart items count
$cart_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_cart_items WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $cart_count = $row['count'];
}
$stmt->close();

if ($cart_count == 0) {
    header('Location: ' . BASE_URL . '/cartview');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['checkout_step1'] = [
        'customer_name' => trim($_POST['customer_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'mobile' => trim($_POST['mobile'] ?? ''),
        'completed' => true
    ];

    header('Location: ' . BASE_URL . '/checkout2');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Step 1: Customer Information - Noble Home</title>

</head>

<body class="bg-gray-100 font-sans">
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <div class="p-4">
        <div class="bg-white p-5 shadow mt-3 max-w-4xl mx-auto">
            <h2 class="text-3xl text-orange-700 mb-8">Checkout Process</h2>
            <!-- Progress Steps -->
            <div class="mb-8">
                <div class="flex items-center justify-between">

                    <!-- Step 1 - Active -->
                    <div class="flex items-center flex-1">
                        <div
                            class="w-10 h-10 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold shrink-0">
                            1
                        </div>
                        <div class="ml-3 hidden sm:block">
                            <div class="font-medium text-orange-600 text-sm">Customer Info</div>
                            <div class="text-xs text-gray-500">Your details</div>
                        </div>
                    </div>

                    <div class="flex-1 h-px bg-gray-300 mx-2"></div>

                    <!-- Step 2 -->
                    <div class="flex items-center flex-1">
                        <div
                            class="w-10 h-10 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center font-bold shrink-0">
                            2
                        </div>
                        <div class="ml-3 hidden sm:block">
                            <div class="font-medium text-gray-400 text-sm">Delivery Address</div>
                            <div class="text-xs text-gray-400">Where to deliver</div>
                        </div>
                    </div>

                    <div class="flex-1 h-px bg-gray-300 mx-2"></div>

                    <!-- Step 3 -->
                    <div class="flex items-center flex-1">
                        <div
                            class="w-10 h-10 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center font-bold shrink-0">
                            3
                        </div>
                        <div class="ml-3 hidden sm:block">
                            <div class="font-medium text-gray-400 text-sm">Delivery Fee</div>
                            <div class="text-xs text-gray-400">Calculate costs</div>
                        </div>
                    </div>

                    <div class="flex-1 h-px bg-gray-300 mx-2"></div>

                    <!-- Step 4 -->
                    <div class="flex items-center flex-1">
                        <div
                            class="w-10 h-10 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center font-bold shrink-0">
                            4
                        </div>
                        <div class="ml-3 hidden sm:block">
                            <div class="font-medium text-gray-400 text-sm">Payment</div>
                            <div class="text-xs text-gray-400">Complete order</div>
                        </div>
                    </div>

                </div>
            </div>

            <form method="POST" class="space-y-6">
                <div class=" p-4 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fa-solid fa-user-clock mr-4 text-2xl"></i>
                        <div>
                            <h3 class="text-lg text-black">Step 1: Customer Information</h3>
                            <p class="text-black text-sm">Please verify your contact details</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block font-medium mb-2 text-gray-700">Full Name *</label>
                        <input type="text" name="customer_name" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            value="<?= htmlspecialchars($userName ?? '') ?>" placeholder="Enter your full name" />
                        <p class="text-xs text-gray-500 mt-1">This name will appear on your order receipt</p>
                    </div>

                    <div>
                        <label class="block font-medium mb-2 text-gray-700">Email Address *</label>
                        <input type="email" name="email" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            value="<?= htmlspecialchars($userEmail ?? '') ?>" placeholder="your.email@example.com" />
                        <p class="text-xs text-gray-500 mt-1">We'll send your order confirmation to this email</p>
                    </div>

                    <div>
                        <label class="block font-medium mb-2 text-gray-700">Mobile Number *</label>
                        <div class="flex">
                            <span
                                class="inline-flex items-center px-3 text-gray-700 bg-gray-200 border border-r-0 border-gray-300 rounded-l-lg">
                                +63
                            </span>
                            <input type="tel" name="mobile" required pattern="9[0-9]{9}"
                                class="flex-1 border border-gray-300 px-4 py-3 rounded-r-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                value="<?= htmlspecialchars($userMobile ?? '') ?>" placeholder="9171234567"
                                maxlength="10" />
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Format: 9XXXXXXXXX (10 digits, without the leading 0)</p>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="text-sm text-blue-800">
                            <strong>Note:</strong> Make sure your contact information is correct. We'll use this to
                            contact
                            you about your order and delivery updates.
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-4">
                    <a href="<?= BASE_URL ?>/cartview" class="text-gray-600 hover:text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                            </path>
                        </svg>
                        Back to Cart
                    </a>

                    <button type="submit"
                        class="bg-orange-600 text-white px-8 py-3 rounded-lg hover:bg-orange-700 transition font-medium flex items-center">
                        Continue to Address
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>

    <script>
        // Mobile number validation
        document.querySelector('input[name="mobile"]').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) value = value.slice(0, 10);
            e.target.value = value;

            // Ensure it starts with 9
            if (value.length > 0 && value[0] !== '9') {
                e.target.setCustomValidity('Mobile number must start with 9 (after +63)');
            } else {
                e.target.setCustomValidity('');
            }
        });

    </script>
</body>

</html>