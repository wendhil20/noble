<?php
//backtracking.php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

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
    header("Location: ../../loginpage/index.php");
    exit();
}

// Handle form submission (PRG pattern - prevents resubmit on refresh)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    $name         = trim($_POST['name']);
    $email        = trim($_POST['email']);
    $contact      = trim($_POST['contact']);
    $company      = trim($_POST['company_name']);
    $message      = trim($_POST['message']);
    $inquiry_date = trim($_POST['inquiry_date']);
    $inquiry_time = trim($_POST['inquiry_time']);
    $submitted_by = $_SESSION['noble_user'];

    if (empty($name) || empty($email) || empty($contact) || empty($company) || empty($inquiry_date) || empty($inquiry_time)) {
        $_SESSION['flash_error'] = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = "Invalid email address.";
    } else {
        $stmt = $conn->prepare("INSERT INTO backtrack (name, email, contact, company_name, message, inquiry_date, inquiry_time, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $name, $email, $contact, $company, $message, $inquiry_date, $inquiry_time, $submitted_by);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Inquiry submitted successfully!";
        } else {
            $_SESSION['flash_error'] = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
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
    <?php include '../navbar/top.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">

<div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Backtrack Inquiry</h1>
        <p class="text-sm text-gray-500 mt-1">Submit and manage client inquiries</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($success_msg): ?>
    <div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <?= htmlspecialchars($success_msg) ?>
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

    <!-- Inquiries Table -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-base font-medium text-gray-700">Inquiry Records</h2>
            <span class="text-xs text-gray-400"><?= count($inquiries) ?> total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Company</th>
                        <th class="px-4 py-3">Message</th>
                        <th class="px-4 py-3">Inquiry Date</th>
                        <th class="px-4 py-3">Inquiry Time</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Submitted By</th>
                        <th class="px-4 py-3">Created At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($inquiries)): ?>
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-gray-400 text-sm">No inquiries found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($inquiries as $i => $row): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-gray-400"><?= $i + 1 ?></td>
                        <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($row['email']) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($row['contact']) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($row['company_name']) ?></td>
                        <td class="px-4 py-3 text-gray-500 max-w-xs truncate"><?= htmlspecialchars($row['message'] ?: '—') ?></td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= htmlspecialchars(isset($row['inquiry_date']) ? date('M d, Y', strtotime($row['inquiry_date'])) : '—') ?></td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= htmlspecialchars(isset($row['inquiry_time']) ? date('h:i A', strtotime($row['inquiry_time'])) : '—') ?></td>
                        <td class="px-4 py-3">
                            <?php
                                $status = $row['status'];
                                $badge = match($status) {
                                    'pending'   => 'bg-yellow-50 text-yellow-700 border border-yellow-200',
                                    'contacted' => 'bg-blue-50 text-blue-700 border border-blue-200',
                                    'resolved'  => 'bg-green-50 text-green-700 border border-green-200',
                                    default     => 'bg-gray-100 text-gray-600 border border-gray-200',
                                };
                            ?>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $badge ?>">
                                <?= ucfirst($status) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($row['submitted_by']) ?></td>
                        <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                            <?= date('M d, Y h:i A', strtotime($row['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>