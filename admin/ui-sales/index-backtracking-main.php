<?php
//backtracking.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: ". BASE_URL ."/main");
    exit();
}

// Generate unique reference number: NH{YEAR}-{10 random digits}
// Loops until no collision found in DB
function generate_reference($conn) {
    $year = date('Y'); // auto-updates every year (2026 → 2027 → etc.)
    do {
        $random = str_pad(mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $ref = "NH{$year}-{$random}";
        $chk = $conn->prepare("SELECT id FROM backtrack WHERE reference_no = ? LIMIT 1");
        $chk->bind_param("s", $ref);
        $chk->execute();
        $chk->store_result();
        $exists = $chk->num_rows > 0;
        $chk->close();
    } while ($exists);
    return $ref;

}

// Handle form submission (PRG pattern - prevents resubmit on refresh)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    $name            = trim($_POST['name']);
    $email           = trim($_POST['email']);
    $contact         = trim($_POST['contact']);
    $company         = trim($_POST['company_name']);
    $company_address = trim($_POST['company_address']);
    $message         = trim($_POST['message']);
    $inquiry_date    = trim($_POST['inquiry_date']);
    $inquiry_time    = trim($_POST['inquiry_time']);
    $submitted_by    = $_SESSION['noble_user'];

    if (empty($name) || empty($email) || empty($contact) || empty($company) || empty($inquiry_date) || empty($inquiry_time)) {
        $_SESSION['flash_error'] = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = "Invalid email address.";
    } else {
        // Check if same name + email already has an existing reference (returning customer)
        $existing_ref = null;
        $chk = $conn->prepare("SELECT reference_no FROM backtrack WHERE name = ? AND email = ? ORDER BY created_at DESC LIMIT 1");
        $chk->bind_param("ss", $name, $email);
        $chk->execute();
        $chk->bind_result($existing_ref);
        $chk->fetch();
        $chk->close();

// Always generate a new unique reference number
$reference_no = generate_reference($conn);

// Count existing inquiries matching same name OR email, then +1
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM backtrack WHERE name = ? OR email = ?");
$count_stmt->bind_param("ss", $name, $email);
$count_stmt->execute();
$count_stmt->bind_result($existing_count);
$count_stmt->fetch();
$count_stmt->close();

$inquiry_number = $existing_count + 1;

$stmt = $conn->prepare("INSERT INTO backtrack (reference_no, name, email, contact, company_name, company_address, message, inquiry_date, inquiry_time, submitted_by, inquiry_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssssssi", $reference_no, $name, $email, $contact, $company, $company_address, $message, $inquiry_date, $inquiry_time, $submitted_by, $inquiry_number);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Inquiry submitted successfully! Reference No: <strong>{$reference_no}</strong>";
        } else {
            $_SESSION['flash_error'] = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }

    header("Location: " . BASE_URL . "/backtracking");
    exit();
}

// Pick up flash messages then clear them
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Fetch all inquiries
$inquiries = [];
$result = $conn->query("SELECT * FROM backtrack ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inquiries[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backtrack - Inquiry</title>
    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">

<div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Page Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">Backtrack Inquiry</h1>
            <p class="text-sm text-gray-500 mt-1">Submit and manage client inquiries</p>
        </div>
        <a href="<?= BASE_URL ?>/backtrackingdashboard" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 active:bg-gray-100 text-gray-700 text-sm font-medium rounded-lg transition shadow-sm">
            Dashboard
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if ($success_msg): ?>
    <div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span><?= $success_msg ?></span>
    </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>

    <!-- Inquiry Form Card -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-8">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-medium text-gray-700">New Inquiry</h2>
        </div>
        <div class="px-6 py-5">
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            placeholder="Juan dela Cruz"
                            value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            required
                        >
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            name="email"
                            placeholder="juan@example.com"
                            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            required
                        >
                    </div>

                    <!-- Contact -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">
                            Contact Number <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="contact"
                            placeholder="09XXXXXXXXX"
                            value="<?= isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : '' ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            required
                        >
                    </div>

                    <!-- Company Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">
                            Company Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="company_name"
                            placeholder="Acme Corporation"
                            value="<?= isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : '' ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            required
                        >
                    </div>

                    <!-- Company Address -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-600 mb-1">
                            Company Address
                        </label>
                        <input
                            type="text"
                            name="company_address"
                            placeholder="123 Rizal St., Makati City, Metro Manila"
                            value="<?= isset($_POST['company_address']) ? htmlspecialchars($_POST['company_address']) : '' ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        >
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">
                            Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="inquiry_date"
                            value="<?= isset($_POST['inquiry_date']) ? htmlspecialchars($_POST['inquiry_date']) : date('Y-m-d') ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            required
                        >
                    </div>

                    <!-- Time -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">
                            Time <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="time"
                            name="inquiry_time"
                            value="<?= isset($_POST['inquiry_time']) ? htmlspecialchars($_POST['inquiry_time']) : date('H:i') ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            required
                        >
                    </div>

                    <!-- Message (full width) -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-600 mb-1">Message</label>
                        <textarea
                            name="message"
                            rows="3"
                            placeholder="Add any additional notes or concerns..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none"
                        ><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                    </div>

                </div>

                <!-- Submit -->
                <div class="mt-4 flex justify-end">
                    <button
                        type="submit"
                        name="submit_inquiry"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-medium rounded-lg transition"
                    >
                        Submit Inquiry
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

</body>
</html>