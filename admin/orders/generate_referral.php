<?php
// generate_referral.php - Complete Referral Code System with Remake Feature
session_name("nobleadmin");
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ ADD THIS NEW CODE - Check for success message from session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it after displaying
}

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get user info from session or database
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_id'] = null;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

// Set user variables
$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];
$user_level = $_SESSION['noble_lvl'];

$message = "";
$error = "";
$referral_data = null;

// ✅ Generate Clean Referral Code (NH-XXXXXX)
function generateCleanCode($conn, $user_id)
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed confusing: 0,O,1,I
    $code = 'NH-';

    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }

    // Check uniqueness
    $stmt = $conn->prepare("SELECT id FROM referral_codes WHERE referral_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        return generateCleanCode($conn, $user_id); // Recursive retry
    }

    $stmt->close();
    return $code;
}

// ✅ Check if user already has an active referral code
$stmt = $conn->prepare("SELECT referral_code, qr_code_path, base_url, total_scans, total_conversions, total_revenue, created_at, discount_enabled, discount_type, discount_value FROM referral_codes WHERE user_id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($existing_code, $qr_path, $base_url, $scans, $conversions, $revenue, $created, $discount_enabled, $discount_type, $discount_value);
if ($stmt->fetch()) {
    $referral_data = [
        'code' => $existing_code,
        'qr_path' => $qr_path,
        'base_url' => $base_url,
        'scans' => $scans,
        'conversions' => $conversions,
        'revenue' => $revenue,
        'created' => $created,
        'discount_enabled' => $discount_enabled,
        'discount_type' => $discount_type,
        'discount_value' => $discount_value
    ];
}
$stmt->close();

// Calculate conversion rate early
if ($referral_data !== null) {
    $total_visits = $referral_data['scans'];
    $total_conversions = $referral_data['conversions'];
    $conversion_rate = $total_visits > 0 ? ($total_conversions / $total_visits) * 100 : 0;
    $referral_data['conversion_rate'] = $conversion_rate;
}

// ✅ CALCULATE TOTAL COMMISSION EARNED (UNCLAIMED ONLY)
if ($referral_data !== null) {
    // Get total commission from UNCLAIMED orders only
    $commission_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(sales_commission_amount), 0) as total_commission,
            COALESCE(SUM(subtotal), 0) as total_sales_value,
            AVG(sales_commission_rate) as avg_commission_rate
        FROM orders 
        WHERE sales_referral_code = ? 
        AND sales_user_id = ?
        AND payment_status IN ('verified', 'paid', 'completed')
        AND commission_claimed = 0
    ");
    $commission_stmt->bind_param("si", $referral_data['code'], $user_id);
    $commission_stmt->execute();
    $commission_result = $commission_stmt->get_result();

    if ($commission_result->num_rows > 0) {
        $commission_data = $commission_result->fetch_assoc();
        $referral_data['total_commission'] = floatval($commission_data['total_commission'] ?? 0);
        $referral_data['commission_orders'] = intval($commission_data['total_orders'] ?? 0);
        $referral_data['total_sales_value'] = floatval($commission_data['total_sales_value'] ?? 0);
        $referral_data['avg_commission_rate'] = floatval($commission_data['avg_commission_rate'] ?? 0);
    } else {
        $referral_data['total_commission'] = 0;
        $referral_data['commission_orders'] = 0;
        $referral_data['total_sales_value'] = 0;
        $referral_data['avg_commission_rate'] = 0;
    }
    $commission_stmt->close();

    // Get detailed commission breakdown (UNCLAIMED ONLY)
    $commission_details_stmt = $conn->prepare("
        SELECT 
            o.id,
            o.reference_no,
            o.customer_name,
            o.created_at,
            o.subtotal,
            o.sales_commission_rate,
            o.sales_commission_amount,
            o.payment_status
        FROM orders o
        WHERE o.sales_referral_code = ? 
        AND o.sales_user_id = ?
        AND o.commission_claimed = 0
        ORDER BY o.created_at DESC
    ");
    $commission_details_stmt->bind_param("si", $referral_data['code'], $user_id);
    $commission_details_stmt->execute();
    $commission_details_result = $commission_details_stmt->get_result();

    $commission_breakdown = [];
    while ($row = $commission_details_result->fetch_assoc()) {
        $commission_breakdown[] = $row;
    }
    $commission_details_stmt->close();
    $referral_data['commission_breakdown'] = $commission_breakdown;

    // ✅ ALSO GET ALREADY CLAIMED COMMISSIONS (for history display)
    $claimed_history_stmt = $conn->prepare("
        SELECT 
            cc.id,
            cc.commission_amount,
            cc.claim_date,
            cc.status,
            cc.released_at,
            cc.order_count
        FROM commission_claims cc
        WHERE cc.sales_user_id = ?
        ORDER BY cc.claim_date DESC
    ");
    $claimed_history_stmt->bind_param("i", $user_id);
    $claimed_history_stmt->execute();
    $claimed_history_result = $claimed_history_stmt->get_result();

    $claimed_history = [];
    while ($row = $claimed_history_result->fetch_assoc()) {
        $claimed_history[] = $row;
    }
    $claimed_history_stmt->close();
    $referral_data['claimed_history'] = $claimed_history;
}

// ✅ NEW: Count unique users who used this referral code
if ($referral_data !== null) {
    // Count unique customers who placed orders with this referral code
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) FROM orders WHERE sales_referral_code = ? AND sales_referral_code IS NOT NULL");
    $stmt->bind_param("s", $referral_data['code']);
    $stmt->execute();
    $stmt->bind_result($unique_users);
    $stmt->fetch();
    $stmt->close();
    $referral_data['unique_users'] = $unique_users ?? 0;

    // Get list of users who used the code (with their details)
    $stmt = $conn->prepare("
    SELECT DISTINCT 
        o.user_id,
        o.customer_name,
        o.email,
        COUNT(o.id) as order_count,
        SUM(o.total) as total_spent,
        MIN(o.created_at) as first_order_date,
        MAX(o.created_at) as last_order_date
    FROM orders o
    WHERE o.sales_referral_code = ? AND o.sales_referral_code IS NOT NULL
    GROUP BY o.user_id, o.customer_name, o.email
    ORDER BY total_spent DESC
");
    $stmt->bind_param("s", $referral_data['code']);
    $stmt->execute();
    $result = $stmt->get_result();

    $users_list = [];
    while ($row = $result->fetch_assoc()) {
        $users_list[] = $row;
    }
    $stmt->close();
    $referral_data['users_list'] = $users_list;
}

// ✅ ADD THIS NEW CODE RIGHT AFTER THE ABOVE BLOCK:
// Get additional date-based analytics
if ($referral_data !== null) {
    // Get today's visits
    $stmt = $conn->prepare("SELECT COUNT(*) FROM referral_visits WHERE user_id = ? AND visit_date = CURDATE()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($today_visits);
    $stmt->fetch();
    $stmt->close();
    $referral_data['today_visits'] = $today_visits ?? 0;

    // Get this week's visits
    $stmt = $conn->prepare("SELECT COUNT(*) FROM referral_visits WHERE user_id = ? AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($week_visits);
    $stmt->fetch();
    $stmt->close();
    $referral_data['week_visits'] = $week_visits ?? 0;

    // Get this month's visits
    $stmt = $conn->prepare("SELECT COUNT(*) FROM referral_visits WHERE user_id = ? AND MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($month_visits);
    $stmt->fetch();
    $stmt->close();
    $referral_data['month_visits'] = $month_visits ?? 0;

    // Get last 30 days daily breakdown with conversions
    $stmt = $conn->prepare("
    SELECT 
        rv.visit_date,
        COUNT(DISTINCT rv.id) as visit_count,
        COALESCE(daily_orders.order_count, 0) as order_count,
        COALESCE(daily_orders.revenue, 0) as daily_revenue
    FROM referral_visits rv
    LEFT JOIN (
        SELECT 
            DATE(created_at) as order_date,
            COUNT(*) as order_count,
            SUM(total) as revenue
        FROM orders
        WHERE sales_referral_code = ? 
        AND sales_referral_code IS NOT NULL
        GROUP BY DATE(created_at)
    ) daily_orders ON rv.visit_date = daily_orders.order_date
    WHERE rv.user_id = ? 
    AND rv.visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY rv.visit_date, daily_orders.order_count, daily_orders.revenue
    ORDER BY rv.visit_date DESC
");
    $stmt->bind_param("si", $referral_data['code'], $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $daily_visits = [];
    while ($row = $result->fetch_assoc()) {
        $daily_visits[] = $row;
    }
    $stmt->close();
    $referral_data['daily_breakdown'] = $daily_visits;

    // Calculate conversion rate for active code
    $total_visits = $referral_data['scans'];
    $total_conversions = $referral_data['conversions'];
    $conversion_rate = $total_visits > 0 ? ($total_conversions / $total_visits) * 100 : 0;
    $referral_data['conversion_rate'] = $conversion_rate;
}

// ✅ Handle form submission to generate new code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {

    // Check if user already has active code
    if ($referral_data !== null) {
        $error = "You already have an active referral code!";
    } else {
        // Determine base URL dynamically
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        // Check if localhost or production
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            $base_url = "http://localhost/noble/user/otherpage/index-page-1-A-B-C-D-E.php";
        } else {
            $base_url = "https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E";
        }

        // Generate new code
        $referral_code = generateCleanCode($conn, $user_id);

        // Insert into database with base_url
        $stmt = $conn->prepare("INSERT INTO referral_codes (user_id, referral_code, base_url, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iss", $user_id, $referral_code, $base_url);

        if ($stmt->execute()) {
            // Save success message to session
            $_SESSION['success_message'] = "Referral code generated successfully!";
            $stmt->close();

            // Redirect to reload the page with fresh data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "Failed to generate referral code. Please try again.";
            $stmt->close();
        }
    }
}
// ✅ Handle REMAKE referral code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remake_code'])) {

    if ($referral_data === null) {
        $error = "No active referral code found!";
    } else {
        // Determine base URL dynamically
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            $base_url = "http://localhost/noble/user/otherpage/index-page-1-A-B-C-D-E.php";
        } else {
            $base_url = "https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E";
        }

        // Deactivate old code
        $stmt = $conn->prepare("UPDATE referral_codes SET is_active = 0 WHERE user_id = ? AND is_active = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Generate new code
        $new_referral_code = generateCleanCode($conn, $user_id);

        // Insert new code with base_url
        $stmt = $conn->prepare("INSERT INTO referral_codes (user_id, referral_code, base_url, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iss", $user_id, $new_referral_code, $base_url);

        if ($stmt->execute()) {
            // Save success message to session
            $_SESSION['success_message'] = "New referral code generated successfully! Your old code has been deactivated.";
            $stmt->close();

            // Redirect to reload the page with fresh data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "Failed to generate new referral code. Please try again.";
            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Referral Code - Noble Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-200 min-h-screen" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
    <?php include '../navbar/top.php'; ?>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8 py-4 sm:py-8">

        <?php if ($message): ?>
            <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4 mb-6 flex items-center animate-pulse">
                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                <span class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 mb-6 flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 text-2xl mr-3"></i>
                <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($referral_data !== null): ?>

            <div class="bg-gray-100  overflow-hidden mb-6">
                <div class="bg-black px-2 sm:px-6 py-3 sm:py-4 flex justify-between items-center">
                    <h2 class="text-lg sm:text-2xl text-white flex items-center">
                        <i class="fa-solid fa-paperclip p-1"></i>
                        Your Active Referral Code
                    </h2>
                    <button onclick="showRemakeModal()"
                        class="bg-white/20 hover:bg-white/30 text-white px-3 sm:px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2 text-sm">
                        <i class="fas fa-sync-alt"></i>
                        <span class="hidden sm:inline">Remake Code</span>
                    </button>
                </div>

                <div class="p-2">
                    <div class="bg-blue-800  p-6 mb-6 text-center">
                        <div class="text-sm text-white font-medium mb-2">YOUR REFERRAL CODE</div>
                        <div class="text-4xl font-bold text-white tracking-wider  mb-4">
                            <?php echo htmlspecialchars($referral_data['code']); ?>
                        </div>
                        <button onclick="copyCode()" class="bg-green-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg transition-colors duration-200 inline-flex items-center space-x-2">
                            <i class="fas fa-copy"></i>
                            <span>Copy Code</span>
                        </button>
                    </div>

                    <div class="mb-6">
                        <label class="block text-lg font-bold text-black mb-2">
                            <i class="fas fa-link mr-1"></i>Referral Link
                        </label>
                        <div class="flex gap-2">
                            <input type="text" id="referralLink" readonly
                                value="<?php
                                        $saved_base_url = $referral_data['base_url'] ?? '';
                                        echo htmlspecialchars($saved_base_url . "?ref=" . $referral_data['code']);
                                        ?>"
                                class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm ">
                            <button onclick="copyLink()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                                <i class="fas fa-link"></i>
                                <span>Copy</span>
                            </button>
                        </div>
                        <p class="text-xs text-black mt-2">Share this link with customers to track your referrals.</p>
                    </div>

                    <!-- QR Code Section -->
                    <div class="mb-6 bg-blue-800  p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-white flex items-center">
                                <i class="fas fa-qrcode mr-2"></i>QR Code
                            </h3>
                            <?php if (!empty($referral_data['qr_path'])): ?>
                                <span class="bg-green-500 px-3 py-1 rounded-full text-xs font-medium text-white">
                                    <i class="fas fa-check-circle mr-1"></i>Generated
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- QR Container - Hidden by default if no QR exists -->
                        <div id="qr-container" class="<?php echo (empty($referral_data['qr_path']) || $referral_data['qr_path'] === null) ? 'hidden' : ''; ?>">
                            <div class="text-center mb-4">
                                <div class="inline-block p-4 bg-white border-2 border-gray-300 rounded-lg shadow-sm">
                                    <div id="qr-display" class="qr-code-display"></div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="downloadQRCode()"
                                    class="flex-1 hover:underline text-white hover:text-white px-4 py-2 rounded-lg  flex items-center justify-center space-x-2">
                                    <i class="fas fa-download"></i>
                                    <span>Download QR</span>
                                </button>
                                <button onclick="regenerateQR()"
                                    class="flex-1 hover:underline text-white hover:text-white px-4 py-2 rounded-lg flex items-center justify-center space-x-2">
                                    <i class="fas fa-sync-alt"></i>
                                    <span>Regenerate</span>
                                </button>
                            </div>
                        </div>

                        <!-- Generate button (shown when no QR exists) -->
                        <div id="qr-generate-section" class="<?php echo (empty($referral_data['qr_path']) || $referral_data['qr_path'] === null) ? '' : 'hidden'; ?>">
                            <div class="text-center py-8">
                                <i class="fas fa-qrcode text-white text-6xl mb-4"></i>
                                <p class="text-white mb-4">No QR code generated yet</p>
                                <button onclick="generateQRCode()"
                                    class="bg-black hover:from-purple-600 hover:to-purple-700 text-white px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 flex items-center justify-center space-x-2 mx-auto">
                                    <i class="fas fa-magic"></i>
                                    <span>Generate QR Code</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Check if there's a pending claim
                    $pending_claim_stmt = $conn->prepare("
    SELECT id, commission_amount, claim_date, status 
    FROM commission_claims 
    WHERE sales_user_id = ? 
    AND status IN ('pending', 'approved')
    ORDER BY claim_date DESC 
    LIMIT 1
");
                    $pending_claim_stmt->bind_param("i", $user_id);
                    $pending_claim_stmt->execute();
                    $pending_claim_result = $pending_claim_stmt->get_result();
                    $has_pending_claim = $pending_claim_result->num_rows > 0;
                    $pending_claim_data = $has_pending_claim ? $pending_claim_result->fetch_assoc() : null;
                    $pending_claim_stmt->close();

                    // Handle claim submission
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_commission'])) {
                        if ($has_pending_claim) {
                            $error = "You already have a pending commission claim!";
                        } elseif (($referral_data['total_commission'] ?? 0) <= 0) {
                            $error = "No commission available to claim!";
                        } else {
                            // Get all unclaimed order IDs
                            $order_ids_stmt = $conn->prepare("
            SELECT id FROM orders 
            WHERE sales_referral_code = ? 
            AND sales_user_id = ?
            AND payment_status IN ('verified', 'paid', 'completed')
            AND commission_claimed = 0
        ");
                            $order_ids_stmt->bind_param("si", $referral_data['code'], $user_id);
                            $order_ids_stmt->execute();
                            $order_ids_result = $order_ids_stmt->get_result();

                            $order_ids = [];
                            while ($row = $order_ids_result->fetch_assoc()) {
                                $order_ids[] = $row['id'];
                            }
                            $order_ids_stmt->close();

                            $order_ids_string = implode(',', $order_ids);
                            $order_count = count($order_ids);

                            $claim_stmt = $conn->prepare("
            INSERT INTO commission_claims (sales_user_id, referral_code, commission_amount, order_ids, order_count) 
            VALUES (?, ?, ?, ?, ?)
        ");
                            $claim_stmt->bind_param("isdsi", $user_id, $referral_data['code'], $referral_data['total_commission'], $order_ids_string, $order_count);

                            if ($claim_stmt->execute()) {
                                $_SESSION['success_message'] = "Commission claim submitted successfully! Waiting for approval.";
                                $claim_stmt->close();
                                header("Location: " . $_SERVER['PHP_SELF']);
                                exit();
                            } else {
                                $error = "Failed to submit claim. Please try again.";
                                $claim_stmt->close();
                            }
                        }
                    }
                    ?>

                    <!-- ✅ COMMISSION EARNINGS SECTION -->
                    <div class="bg-white overflow-hidden mb-6">
                        <div class="bg-black px-4 py-3 flex items-center justify-between">
                            <h3 class="text-white flex items-center text-lg">
                                <i class="fas fa-money-bill-wave mr-2 text-white "></i>
                                Your Commission Earnings
                            </h3>
                            <span class="bg-white/20 px-3 py-1 rounded-full text-white text-xs font-semibold">
                                <i class="fas fa-percent mr-1"></i>
                                <?php echo number_format($referral_data['avg_commission_rate'] ?? 0, 1); ?>% Avg Rate
                            </span>
                        </div>

                        <div class="p-6">
                            <!-- Claim Status Banner -->
                            <?php if ($has_pending_claim): ?>
                                <div class="mb-6 bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-start space-x-3">
                                            <?php if ($pending_claim_data['status'] === 'pending'): ?>
                                                <i class="fas fa-clock text-yellow-600 text-2xl mt-1"></i>
                                                <div>
                                                    <h4 class="font-bold text-yellow-900">Claim Pending Approval</h4>
                                                    <p class="text-sm text-yellow-700 mt-1">
                                                        Your commission claim of <strong>₱<?php echo number_format($pending_claim_data['commission_amount'], 2); ?></strong>
                                                        is waiting for superadmin approval.
                                                    </p>
                                                    <p class="text-xs text-yellow-600 mt-1">
                                                        Submitted on <?php echo date('M j, Y g:i A', strtotime($pending_claim_data['claim_date'])); ?>
                                                    </p>
                                                </div>
                                            <?php elseif ($pending_claim_data['status'] === 'approved'): ?>
                                                <i class="fas fa-check-circle text-green-600 text-2xl mt-1"></i>
                                                <div>
                                                    <h4 class="font-bold text-green-900">Claim Approved - Pending Release</h4>
                                                    <p class="text-sm text-green-700 mt-1">
                                                        Your claim of <strong>₱<?php echo number_format($pending_claim_data['commission_amount'], 2); ?></strong>
                                                        has been approved! Waiting for accountant to release payment.
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!-- Commission Summary Cards -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <!-- Total Commission -->
                                <div class="bg-white  text-center">
                                    <div class="text-sm text-gray-600 mb-2 font-medium">Total Commission Earned</div>
                                    <div class="text-4xl font-bold text-green-600 mb-1">
                                        ₱<?php echo number_format($referral_data['total_commission'] ?? 0, 2); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        From <?php echo number_format($referral_data['commission_orders'] ?? 0); ?> order<?php echo ($referral_data['commission_orders'] ?? 0) != 1 ? 's' : ''; ?>
                                    </div>

                                    <!-- Claim Button -->
                                    <?php if (!$has_pending_claim && ($referral_data['total_commission'] ?? 0) > 0): ?>
                                        <form method="POST" class="mt-3">
                                            <button type="submit" name="claim_commission"
                                                class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-4 py-2 rounded-lg transition-all duration-200 text-sm font-medium w-full flex items-center justify-center space-x-2">
                                                <i class="fas fa-hand-holding-usd"></i>
                                                <span>Claim Commission</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <!-- Total Sales Value -->
                                <div class="bg-white text-center ">
                                    <div class="text-sm text-gray-600 mb-2 font-medium">Total Sales Value</div>
                                    <div class="text-4xl font-bold text-green-600 mb-1">
                                        ₱<?php echo number_format($referral_data['total_sales_value'] ?? 0, 2); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Generated from your referrals
                                    </div>
                                </div>

                                <!-- Average Commission Per Order -->
                                <div class="bg-white text-center">
                                    <div class="text-sm text-gray-600 mb-2 font-medium">Avg Commission/Order</div>
                                    <div class="text-4xl font-bold text-green-600 mb-1">
                                        ₱<?php
                                            $avg_per_order = ($referral_data['commission_orders'] ?? 0) > 0
                                                ? ($referral_data['total_commission'] ?? 0) / ($referral_data['commission_orders'] ?? 1)
                                                : 0;
                                            echo number_format($avg_per_order, 2);
                                            ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Per successful order
                                    </div>
                                </div>
                            </div>

                            <!-- Commission Breakdown Table -->
                            <?php if (!empty($referral_data['commission_breakdown'])): ?>
                                <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
                                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                        <h4 class="font-bold text-gray-800 flex items-center">
                                            <i class="fas fa-list mr-2"></i>
                                            Commission Breakdown
                                        </h4>
                                    </div>

                                    <div class="overflow-x-auto max-h-96">
                                        <table class="w-full text-sm">
                                            <thead class="bg-gray-100 sticky top-0">
                                                <tr class="border-b border-gray-200">
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Order #</th>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Customer</th>
                                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Date</th>
                                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Order Value</th>
                                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Rate</th>
                                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Commission</th>
                                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <?php foreach ($referral_data['commission_breakdown'] as $order): ?>
                                                    <tr class="hover:bg-green-50 transition-colors">
                                                        <td class="px-4 py-3">
                                                            <span class="font-mono text-purple-700 font-semibold">
                                                                <?php echo htmlspecialchars($order['reference_no']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 text-gray-800">
                                                            <?php echo htmlspecialchars($order['customer_name']); ?>
                                                        </td>
                                                        <td class="px-4 py-3 text-center text-xs text-gray-600">
                                                            <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                                        </td>
                                                        <td class="px-4 py-3 text-right font-semibold text-blue-600">
                                                            ₱<?php echo number_format($order['subtotal'], 2); ?>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <span class="inline-flex items-center px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs font-bold">
                                                                <?php echo number_format($order['sales_commission_rate'], 1); ?>%
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 text-right">
                                                            <span class="font-bold text-green-700 text-base">
                                                                ₱<?php echo number_format($order['sales_commission_amount'], 2); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <?php
                                                            $status_colors = [
                                                                'verified' => 'bg-green-100 text-green-700',
                                                                'paid' => 'bg-blue-100 text-blue-700',
                                                                'completed' => 'bg-purple-100 text-purple-700',
                                                                'pending' => 'bg-yellow-100 text-yellow-700'
                                                            ];
                                                            $status_class = $status_colors[$order['payment_status']] ?? 'bg-gray-100 text-gray-700';
                                                            ?>
                                                            <span class="inline-flex items-center px-2 py-1 <?php echo $status_class; ?> rounded-full text-xs font-semibold">
                                                                <?php echo ucfirst($order['payment_status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- ✅ CLAIMED COMMISSION HISTORY -->
                                <?php if (!empty($referral_data['claimed_history'])): ?>
                                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border-2 border-gray-300 overflow-hidden mt-6">
                                        <div class="bg-gradient-to-r from-gray-600 to-gray-700 px-4 py-3 flex items-center justify-between">
                                            <h3 class="text-white font-bold flex items-center text-lg">
                                                <i class="fas fa-history mr-2"></i>
                                                Claimed Commission History
                                            </h3>
                                        </div>

                                        <div class="p-6">
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-sm">
                                                    <thead class="bg-gray-200 border-b-2 border-gray-300">
                                                        <tr>
                                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Claim Date</th>
                                                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Orders</th>
                                                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Amount</th>
                                                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Status</th>
                                                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Released Date</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-200">
                                                        <?php foreach ($referral_data['claimed_history'] as $history): ?>
                                                            <tr class="hover:bg-gray-50">
                                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                                    <?php echo date('M j, Y g:i A', strtotime($history['claim_date'])); ?>
                                                                </td>
                                                                <td class="px-4 py-3 text-center">
                                                                    <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold">
                                                                        <?php echo number_format($history['order_count']); ?> orders
                                                                    </span>
                                                                </td>
                                                                <td class="px-4 py-3 text-right font-bold text-green-700">
                                                                    ₱<?php echo number_format($history['commission_amount'], 2); ?>
                                                                </td>
                                                                <td class="px-4 py-3 text-center">
                                                                    <?php
                                                                    $status_colors = [
                                                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                                                        'approved' => 'bg-blue-100 text-blue-700',
                                                                        'released' => 'bg-green-100 text-green-700',
                                                                        'rejected' => 'bg-red-100 text-red-700'
                                                                    ];
                                                                    $status_class = $status_colors[$history['status']] ?? 'bg-gray-100 text-gray-700';
                                                                    ?>
                                                                    <span class="inline-flex items-center px-3 py-1 <?php echo $status_class; ?> rounded-full text-xs font-bold">
                                                                        <?php echo ucfirst($history['status']); ?>
                                                                    </span>
                                                                </td>
                                                                <td class="px-4 py-3 text-center text-sm text-gray-600">
                                                                    <?php
                                                                    if ($history['released_at']) {
                                                                        echo date('M j, Y', strtotime($history['released_at']));
                                                                    } else {
                                                                        echo '-';
                                                                    }
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class=" p-12 text-center">
                                    <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-600 font-medium">No commission earned yet</p>
                                    <p class="text-sm text-gray-500 mt-2">Share your referral code to start earning commissions!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ✅ CUSTOMERS WHO USED CODE SECTION -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mt-6">
                        <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-4 sm:px-6 py-3 sm:py-4">
                            <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-users mr-2 sm:mr-3"></i>
                                Customers Using Your Code
                            </h2>
                        </div>

                        <div class="p-4 sm:p-6">
                            <?php if (!empty($referral_data['users_list'])): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-100 border-b-2 border-gray-300">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">#</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Customer Name</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Email</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Orders</th>
                                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Total Spent</th>
                                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Comission</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">First Order</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Last Order</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php $counter = 1; ?>
                                            <?php foreach ($referral_data['users_list'] as $user): ?>
                                                <tr class="hover:bg-orange-50 transition-colors">
                                                    <td class="px-4 py-3 text-gray-600 font-medium"><?php echo $counter++; ?></td>

                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center space-x-2">
                                                            <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                                                <span class="text-orange-600 font-bold text-xs">
                                                                    <?php echo strtoupper(substr($user['customer_name'], 0, 1)); ?>
                                                                </span>
                                                            </div>
                                                            <span class="font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($user['customer_name']); ?>
                                                            </span>
                                                        </div>
                                                    </td>

                                                    <td class="px-4 py-3 text-gray-600 text-sm">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </td>

                                                    <td class="px-4 py-3 text-center">
                                                        <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">
                                                            <i class="fas fa-shopping-bag mr-1"></i>
                                                            <?php echo number_format($user['order_count']); ?>
                                                        </span>
                                                    </td>

                                                    <td class="px-4 py-3 text-right">
                                                        <span class="font-bold text-green-700">
                                                            ₱<?php echo number_format($user['total_spent'], 2); ?>
                                                        </span>
                                                    </td>

                                                    <td class="px-4 py-3 text-center text-xs text-gray-600">
                                                        <?php
                                                        // Calculate commission for this customer
                                                        $customer_comm_stmt = $conn->prepare("
        SELECT COALESCE(SUM(sales_commission_amount), 0) as customer_commission
        FROM orders 
        WHERE user_id = ? 
        AND sales_referral_code = ?
        AND sales_user_id = ?
    ");
                                                        $customer_comm_stmt->bind_param("isi", $user['user_id'], $referral_data['code'], $user_id);
                                                        $customer_comm_stmt->execute();
                                                        $customer_comm_stmt->bind_result($customer_commission);
                                                        $customer_comm_stmt->fetch();
                                                        $customer_comm_stmt->close();
                                                        ?>
                                                        <span class="font-bold text-green-700">
                                                            ₱<?php echo number_format($customer_commission ?? 0, 2); ?>
                                                        </span>
                                                    </td>

                                                    <td class="px-4 py-3 text-center text-xs text-gray-600">
                                                        <?php echo date('M j, Y', strtotime($user['first_order_date'])); ?>
                                                    </td>

                                                    <td class="px-4 py-3 text-center text-xs text-gray-600">
                                                        <?php echo date('M j, Y', strtotime($user['last_order_date'])); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Summary -->
                                <div class="mt-4 bg-orange-50 rounded-lg p-4 border border-orange-200">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-700">
                                            <i class="fas fa-users mr-2 text-orange-600"></i>
                                            <strong><?php echo count($referral_data['users_list']); ?></strong> unique customer<?php echo count($referral_data['users_list']) != 1 ? 's' : ''; ?> used your referral code
                                        </span>
                                        <span class="text-gray-600">
                                            Average spending: <strong class="text-green-700">₱<?php
                                                                                                $avg_spending = count($referral_data['users_list']) > 0
                                                                                                    ? array_sum(array_column($referral_data['users_list'], 'total_spent')) / count($referral_data['users_list'])
                                                                                                    : 0;
                                                                                                echo number_format($avg_spending, 2);
                                                                                                ?></strong>
                                        </span>
                                    </div>
                                </div>

                            <?php else: ?>
                                <div class="text-center py-12 text-gray-500">
                                    <i class="fas fa-user-slash text-5xl mb-4 text-gray-300"></i>
                                    <p class="text-lg font-medium">No customers yet</p>
                                    <p class="text-sm">Share your referral code to start getting customers!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>


                </div>
            </div>

            <!-- ✅ ANALYTICS SECTION STARTS HERE -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mt-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-4 sm:px-6 py-3 sm:py-4">
                    <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-chart-line mr-2 sm:mr-3"></i>
                        Visit Analytics
                    </h2>
                </div>

                <div class="p-4 sm:p-6">
                    <?php
                    // Get today's conversion rate
                    $today_orders_stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE sales_referral_code = ? AND DATE(created_at) = CURDATE()");
                    $today_orders_stmt->bind_param("s", $referral_data['code']);
                    $today_orders_stmt->execute();
                    $today_orders_stmt->bind_result($today_orders);
                    $today_orders_stmt->fetch();
                    $today_orders_stmt->close();

                    $today_visits = $referral_data['today_visits'] ?? 0;
                    $today_conversion = $today_visits > 0 ? (($today_orders ?? 0) / $today_visits) * 100 : 0;

                    // Calculate this week's stats
                    $week_orders_stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE sales_referral_code = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                    $week_orders_stmt->bind_param("s", $referral_data['code']);
                    $week_orders_stmt->execute();
                    $week_orders_stmt->bind_result($week_orders);
                    $week_orders_stmt->fetch();
                    $week_orders_stmt->close();

                    $week_visits = $referral_data['week_visits'] ?? 0;
                    $week_conversion = $week_visits > 0 ? (($week_orders ?? 0) / $week_visits) * 100 : 0;

                    // Calculate this month's stats
                    $month_orders_stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE sales_referral_code = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                    $month_orders_stmt->bind_param("s", $referral_data['code']);
                    $month_orders_stmt->execute();
                    $month_orders_stmt->bind_result($month_orders);
                    $month_orders_stmt->fetch();
                    $month_orders_stmt->close();

                    $month_visits = $referral_data['month_visits'] ?? 0;
                    $month_conversion = $month_visits > 0 ? (($month_orders ?? 0) / $month_visits) * 100 : 0;
                    ?>

                    <!-- Time Period Stats -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-green-600 mb-1">
                                <?php echo number_format($today_visits); ?>
                            </div>
                            <div class="text-sm text-green-700 font-medium">Today's Visits</div>
                            <div class="text-xs text-green-600 mt-1">
                                <i class="fas fa-calendar-day mr-1"></i><?php echo date('F j, Y'); ?>
                            </div>
                            <div class="text-xs text-green-600 mt-1">
                                <i class="fas fa-shopping-cart mr-1"></i>
                                <?php echo number_format($today_conversion, 1); ?>% conversion
                            </div>
                            <?php if ($today_orders > 0): ?>
                                <div class="text-xs text-green-700 mt-1 font-semibold">
                                    <i class="fas fa-check-circle mr-1"></i><?php echo $today_orders; ?> order<?php echo $today_orders != 1 ? 's' : ''; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-blue-600 mb-1">
                                <?php echo number_format($week_visits); ?>
                            </div>
                            <div class="text-sm text-blue-700 font-medium">This Week</div>
                            <div class="text-xs text-blue-600 mt-1">
                                <i class="fas fa-calendar-week mr-1"></i>Last 7 days
                            </div>
                            <div class="text-xs text-blue-600 mt-1">
                                <i class="fas fa-shopping-cart mr-1"></i>
                                <?php echo number_format($week_conversion, 1); ?>% conversion
                            </div>
                            <?php if ($week_orders > 0): ?>
                                <div class="text-xs text-blue-700 mt-1 font-semibold">
                                    <i class="fas fa-check-circle mr-1"></i><?php echo $week_orders; ?> order<?php echo $week_orders != 1 ? 's' : ''; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-purple-600 mb-1">
                                <?php echo number_format($month_visits); ?>
                            </div>
                            <div class="text-sm text-purple-700 font-medium">This Month</div>
                            <div class="text-xs text-purple-600 mt-1">
                                <i class="fas fa-calendar-alt mr-1"></i><?php echo date('F Y'); ?>
                            </div>
                            <div class="text-xs text-purple-600 mt-1">
                                <i class="fas fa-shopping-cart mr-1"></i>
                                <?php echo number_format($month_conversion, 1); ?>% conversion
                            </div>
                            <?php if ($month_orders > 0): ?>
                                <div class="text-xs text-purple-700 mt-1 font-semibold">
                                    <i class="fas fa-check-circle mr-1"></i><?php echo $month_orders; ?> order<?php echo $month_orders != 1 ? 's' : ''; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Daily Breakdown Table -->
                    <div class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 bg-gray-100 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-700">
                                <i class="fas fa-calendar mr-2"></i>Daily Performance (Last 30 Days)
                            </h3>
                        </div>

                        <div class="overflow-x-auto max-h-64">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr class="border-b border-gray-200">
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-600 uppercase">Date</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-600 uppercase">Day</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 uppercase">Visits</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 uppercase">Orders</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 uppercase">Conv. Rate</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-600 uppercase">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (!empty($referral_data['daily_breakdown'])): ?>
                                        <?php foreach ($referral_data['daily_breakdown'] as $day): ?>
                                            <?php
                                            $day_visits = $day['visit_count'];
                                            $day_orders = $day['order_count'];
                                            $day_revenue = $day['daily_revenue'];
                                            $day_conversion = $day_visits > 0 ? ($day_orders / $day_visits) * 100 : 0;
                                            $has_orders = $day_orders > 0;
                                            ?>
                                            <tr class="hover:bg-blue-50 transition-colors <?= $has_orders ? 'bg-green-50' : '' ?>">
                                                <td class="px-4 py-2 text-gray-800 font-medium">
                                                    <?php echo date('M j, Y', strtotime($day['visit_date'])); ?>
                                                </td>
                                                <td class="px-4 py-2 text-gray-600">
                                                    <?php echo date('l', strtotime($day['visit_date'])); ?>
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                                        <i class="fas fa-eye mr-1"></i>
                                                        <?php echo number_format($day_visits); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    <?php if ($has_orders): ?>
                                                        <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-bold">
                                                            <i class="fas fa-shopping-cart mr-1"></i>
                                                            <?php echo number_format($day_orders); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-xs">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    <?php if ($has_orders): ?>
                                                        <span class="font-bold <?= $day_conversion >= 10 ? 'text-green-700' : 'text-orange-600' ?>">
                                                            <?php echo number_format($day_conversion, 1); ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-xs">0%</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-2 text-right">
                                                    <?php if ($has_orders): ?>
                                                        <span class="font-bold text-purple-700">
                                                            ₱<?php echo number_format($day_revenue, 2); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-xs">₱0.00</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                                <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                                No activity recorded yet
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Quick Stats Footer -->
                    <div class="mt-4 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 gap-2">
                        <span>
                            <i class="fas fa-info-circle mr-1"></i>
                            Visit tracking started on <?php echo isset($referral_data['created']) ? date('M j, Y', strtotime($referral_data['created'])) : 'N/A'; ?>
                        </span>
                        <span>
                            <i class="fas fa-users mr-1"></i>
                            Total Unique Visits: <strong class="text-gray-700"><?php echo number_format($referral_data['scans'] ?? 0); ?></strong>
                        </span>
                    </div>
                </div>
            </div>
            <!-- ✅ ANALYTICS SECTION ENDS HERE -->

            <!-- ✅ REFERRAL HISTORY SECTION STARTS HERE -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mt-6">
                <div class="bg-gradient-to-r from-gray-600 to-gray-700 px-4 sm:px-6 py-3 sm:py-4">
                    <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-history mr-2 sm:mr-3"></i>
                        Referral Code History
                    </h2>
                </div>

                <div class="p-4 sm:p-6">
                    <?php
                    // Fetch ALL referral codes (active and inactive) for this user
                    $history_stmt = $conn->prepare("
    SELECT 
        referral_code, 
        is_active, 
        total_scans, 
        total_conversions, 
        total_revenue, 
        discount_enabled,
        discount_type,
        discount_value,
        created_at,
        updated_at
    FROM referral_codes 
    WHERE user_id = ? 
    ORDER BY is_active DESC, created_at DESC
");
                    $history_stmt->bind_param("i", $user_id);
                    $history_stmt->execute();
                    $history_result = $history_stmt->get_result();
                    $has_history = $history_result->num_rows > 0;

                    // Calculate summary stats
                    $active_count = 0;
                    $inactive_count = 0;
                    $all_history_rows = [];

                    while ($row = $history_result->fetch_assoc()) {
                        $all_history_rows[] = $row;

                        if ($row['is_active'] == 1) {
                            $active_count++;
                        } else {
                            $inactive_count++;
                        }
                    }
                    ?>

                    <?php if ($has_history): ?>
                        <?php
                        // NOW calculate real totals by fetching actual data
                        $total_all_visits = 0;
                        $total_all_sales = 0;
                        $total_all_revenue = 0;
                        ?>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100 border-b-2 border-gray-300">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Customer Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Email</th>
                                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Orders</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Total Spent</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Commission Earned</th>
                                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">First Order</th>
                                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Last Order</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($all_history_rows as $history_row): ?>
                                        <?php
                                        // Get REAL visit count from referral_visits table
                                        $visit_stmt = $conn->prepare("SELECT COUNT(*) FROM referral_visits WHERE referral_code = ?");
                                        $visit_stmt->bind_param("s", $history_row['referral_code']);
                                        $visit_stmt->execute();
                                        $visit_stmt->bind_result($real_visits);
                                        $visit_stmt->fetch();
                                        $visit_stmt->close();

                                        // Get REAL order count from orders table
                                        $order_stmt = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(total), 0) FROM orders WHERE sales_referral_code = ? AND sales_referral_code IS NOT NULL");
                                        $order_stmt->bind_param("s", $history_row['referral_code']);
                                        $order_stmt->execute();
                                        $order_stmt->bind_result($real_orders, $real_revenue);
                                        $order_stmt->fetch();
                                        $order_stmt->close();

                                        // Use real data instead of cached totals
                                        $hist_visits = intval($real_visits ?? 0);
                                        $hist_orders = intval($real_orders ?? 0);
                                        $hist_revenue = floatval($real_revenue ?? 0);
                                        $hist_conv_rate = $hist_visits > 0 ? ($hist_orders / $hist_visits) * 100 : 0;

                                        // Add to totals for summary
                                        $total_all_visits += $hist_visits;
                                        $total_all_sales += $hist_orders;
                                        $total_all_revenue += $hist_revenue;
                                        ?>
                                        <tr class="hover:bg-gray-50 transition-colors <?php echo $history_row['is_active'] == 1 ? 'bg-green-50' : ''; ?>">
                                            <!-- Status Badge -->
                                            <td class="px-4 py-3">
                                                <?php if ($history_row['is_active'] == 1): ?>
                                                    <span class="inline-flex items-center px-3 py-1 bg-green-500 text-white rounded-full text-xs font-bold shadow-sm">
                                                        <i class="fas fa-check-circle mr-1"></i>ACTIVE
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-3 py-1 bg-gray-400 text-white rounded-full text-xs font-bold">
                                                        <i class="fas fa-ban mr-1"></i>INACTIVE
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Referral Code -->
                                            <td class="px-4 py-3">
                                                <div class="flex items-center space-x-2">
                                                    <span class="font-mono font-bold text-purple-700 text-base">
                                                        <?php echo htmlspecialchars($history_row['referral_code']); ?>
                                                    </span>
                                                    <?php if ($history_row['is_active'] == 1): ?>
                                                        <button onclick="copyHistoryCode('<?php echo htmlspecialchars($history_row['referral_code']); ?>')"
                                                            class="text-purple-600 hover:text-purple-800 transition" title="Copy code">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- Discount Info -->
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($history_row['discount_enabled'] == 1): ?>
                                                    <span class="inline-flex items-center px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs font-bold">
                                                        <i class="fas fa-ticket-alt mr-1"></i>
                                                        <?php
                                                        if ($history_row['discount_type'] == 'percentage') {
                                                            echo number_format($history_row['discount_value'], 0) . '%';
                                                        } else {
                                                            echo '₱' . number_format($history_row['discount_value'], 0);
                                                        }
                                                        ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Visits -->
                                            <td class="px-4 py-3 text-center">
                                                <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">
                                                    <i class="fas fa-eye mr-1"></i>
                                                    <?php echo number_format($hist_visits); ?>
                                                </span>
                                            </td>

                                            <!-- Orders -->
                                            <td class="px-4 py-3 text-center">
                                                <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-semibold">
                                                    <i class="fas fa-shopping-cart mr-1"></i>
                                                    <?php echo number_format($hist_orders); ?>
                                                </span>
                                            </td>

                                            <!-- Conversion Rate -->
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($hist_orders > 0): ?>
                                                    <span class="font-bold <?php echo $hist_conv_rate >= 10 ? 'text-green-700' : ($hist_conv_rate >= 5 ? 'text-orange-600' : 'text-red-600'); ?>">
                                                        <?php echo number_format($hist_conv_rate, 1); ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">0%</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Revenue -->
                                            <td class="px-4 py-3 text-right">
                                                <span class="font-bold text-purple-700">
                                                    ₱<?php echo number_format($hist_revenue, 2); ?>
                                                </span>
                                            </td>

                                            <!-- Created Date -->
                                            <td class="px-4 py-3 text-center text-xs text-gray-600">
                                                <div class="flex flex-col">
                                                    <span class="font-medium"><?php echo date('M j, Y', strtotime($history_row['created_at'])); ?></span>
                                                    <span class="text-gray-400"><?php echo date('g:i A', strtotime($history_row['created_at'])); ?></span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Summary Stats -->
                        <div class="mt-6 grid grid-cols-2 sm:grid-cols-5 gap-3 bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600"><?php echo $active_count; ?></div>
                                <div class="text-xs text-gray-600">Active Codes</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-400"><?php echo $inactive_count; ?></div>
                                <div class="text-xs text-gray-600">Inactive Codes</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600"><?php echo number_format($total_all_visits); ?></div>
                                <div class="text-xs text-gray-600">Total Visits</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600"><?php echo number_format($total_all_sales); ?></div>
                                <div class="text-xs text-gray-600">Total Sales</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">₱<?php echo number_format($total_all_revenue, 2); ?></div>
                                <div class="text-xs text-gray-600">Total Revenue</div>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-inbox text-5xl mb-4 text-gray-300"></i>
                            <p class="text-lg font-medium">No referral history yet</p>
                            <p class="text-sm">Your referral codes will appear here once you generate them</p>
                        </div>
                    <?php endif; ?>

                    <?php $history_stmt->close(); ?>
                </div>
            </div>
            <!-- ✅ REFERRAL HISTORY SECTION ENDS HERE -->

        <?php else: ?>

            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="p-8 sm:p-12 text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-purple-100 rounded-full mb-6">
                        <i class="fas fa-gift text-purple-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">No Referral Code Yet</h3>
                    <p class="text-gray-600 mb-8 max-w-md mx-auto">
                        Generate your unique referral code to start earning commissions from customer referrals!
                    </p>

                    <form method="POST">
                        <button type="submit" name="generate_code"
                            class="inline-flex items-center space-x-2 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white px-8 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg text-lg font-medium">
                            <i class="fas fa-magic"></i>
                            <span>Generate My Referral Code</span>
                        </button>
                    </form>

                    <div class="mt-8 pt-8 border-t border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">How It Works:</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-left max-w-2xl mx-auto">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <span class="text-purple-600 font-bold">1</span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">Share Your Code</div>
                                    <div class="text-xs text-gray-600">Give your code to customers</div>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <span class="text-purple-600 font-bold">2</span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">They Purchase</div>
                                    <div class="text-xs text-gray-600">Customer completes order</div>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <span class="text-purple-600 font-bold">3</span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">Earn Commission</div>
                                    <div class="text-xs text-gray-600">Get credited for the sale</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <!-- Remake Code Modal -->
    <div id="remakeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 transform transition-all">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-yellow-100 rounded-full mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Remake Referral Code?</h3>
                <p class="text-gray-600 text-sm">
                    This will deactivate your current code <strong class="text-purple-600"><?php echo htmlspecialchars($referral_data['code'] ?? ''); ?></strong> and generate a new one.
                </p>
                <p class="text-red-600 text-xs mt-2 font-medium">
                    ⚠️ Your old code will no longer work!
                </p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-600">Current Stats:</span>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <div class="font-bold text-blue-600"><?php echo number_format($referral_data['scans'] ?? 0); ?></div>
                        <div class="text-xs text-gray-500">Visits</div>
                    </div>
                    <div>
                        <div class="font-bold text-green-600"><?php echo number_format($referral_data['conversions'] ?? 0); ?></div>
                        <div class="text-xs text-gray-500">Sales</div>
                    </div>
                    <div>
                        <div class="font-bold text-purple-600">₱<?php echo number_format($referral_data['revenue'] ?? 0, 2); ?></div>
                        <div class="text-xs text-gray-500">Revenue</div>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center">
                    These stats will be preserved in history
                </p>
            </div>

            <form method="POST">
                <div class="flex gap-3">
                    <button type="button" onclick="hideRemakeModal()"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg transition-colors duration-200 font-medium">
                        Cancel
                    </button>
                    <button type="submit" name="remake_code"
                        class="flex-1 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white px-4 py-2 rounded-lg transition-all duration-200 font-medium">
                        Generate New Code
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function copyCode() {
            const code = '<?php echo $referral_data['code'] ?? ''; ?>';
            navigator.clipboard.writeText(code).then(() => {
                showNotification('✓ Referral code copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy:', err);
                showNotification('Failed to copy code', 'error');
            });
        }

        function copyLink() {
            const linkInput = document.getElementById('referralLink');
            linkInput.select();
            navigator.clipboard.writeText(linkInput.value).then(() => {
                showNotification('✓ Referral link copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy:', err);
                showNotification('Failed to copy link', 'error');
            });
        }

        function showRemakeModal() {
            document.getElementById('remakeModal').classList.remove('hidden');
        }

        function hideRemakeModal() {
            document.getElementById('remakeModal').classList.add('hidden');
        }

        function showNotification(message, type) {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500'
            };

            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-pulse`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Close modal when clicking outside
        document.getElementById('remakeModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                hideRemakeModal();
            }
        });

        function copyCode() {
            const code = '<?php echo $referral_data['code'] ?? ''; ?>';
            navigator.clipboard.writeText(code).then(() => {
                showNotification('✓ Referral code copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy:', err);
                showNotification('Failed to copy code', 'error');
            });
        }

        // ✅ ADD THIS NEW FUNCTION
        function copyHistoryCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                showNotification('✓ Code "' + code + '" copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy:', err);
                showNotification('Failed to copy code', 'error');
            });
        }

        // QR Code Functions
        let qrCodeGenerated = false;

        function generateQRCode() {
            const referralLink = document.getElementById('referralLink').value;

            if (!referralLink) {
                showNotification('Referral link not found', 'error');
                return;
            }

            // Show the QR container
            const qrContainer = document.getElementById('qr-container');
            const generateSection = document.getElementById('qr-generate-section');

            // Clear previous QR if any
            const qrDisplay = document.getElementById('qr-display');
            if (qrDisplay) {
                qrDisplay.innerHTML = '';

                // Generate QR code
                new QRCode(qrDisplay, {
                    text: referralLink,
                    width: 200,
                    height: 200,
                    colorDark: "#f97316",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });

                qrCodeGenerated = true;

                // Show QR container and hide generate button
                if (qrContainer) qrContainer.classList.remove('hidden');
                if (generateSection) generateSection.classList.add('hidden');

                // Save to database
                saveQRCodeToDatabase(referralLink);
            } else {
                showNotification('QR display element not found', 'error');
            }
        }

        function saveQRCodeToDatabase(qrData) {
            fetch('save_referral_qr.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        qr_data: qrData
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('✓ QR code generated and saved! Reloading...', 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showNotification('Failed to save QR code: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to save QR code', 'error');
                });
        }

        function downloadQRCode() {
            const qrCanvas = document.querySelector('#qr-display canvas');

            if (!qrCanvas) {
                showNotification('QR code not found. Please generate it first.', 'error');
                return;
            }

            const referralCode = '<?php echo $referral_data['code'] ?? ''; ?>';
            const userName = '<?php echo htmlspecialchars($fullname); ?>';

            // Create enhanced canvas with info
            const finalCanvas = document.createElement('canvas');
            const ctx = finalCanvas.getContext('2d');

            const qrSize = 300;
            const padding = 20;
            const infoHeight = 150;
            finalCanvas.width = qrSize + (padding * 2);
            finalCanvas.height = qrSize + infoHeight + (padding * 3);

            // White background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, finalCanvas.width, finalCanvas.height);

            // Border
            ctx.strokeStyle = '#f97316';
            ctx.lineWidth = 3;
            ctx.strokeRect(5, 5, finalCanvas.width - 10, finalCanvas.height - 10);

            // Draw QR code
            ctx.drawImage(qrCanvas, padding, padding, qrSize, qrSize);

            // Info section
            let yPos = qrSize + padding * 2 + 20;

            // Title
            ctx.fillStyle = '#1f2937';
            ctx.font = 'bold 20px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('REFERRAL CODE', finalCanvas.width / 2, yPos);

            yPos += 30;

            // Referral Code
            ctx.fillStyle = '#f97316';
            ctx.font = 'bold 24px monospace';
            ctx.fillText(referralCode, finalCanvas.width / 2, yPos);

            yPos += 30;

            // Sales Representative
            ctx.fillStyle = '#6b7280';
            ctx.font = '12px Arial';
            ctx.fillText('Sales Representative:', finalCanvas.width / 2, yPos);

            yPos += 18;

            ctx.fillStyle = '#374151';
            ctx.font = 'bold 14px Arial';
            ctx.fillText(userName, finalCanvas.width / 2, yPos);

            // Convert to blob and download
            finalCanvas.toBlob(function(blob) {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Referral_QR_${referralCode}.png`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                showNotification('✓ QR code downloaded!', 'success');
            }, 'image/png');
        }

        function regenerateQR() {
            if (!confirm('Are you sure you want to regenerate the QR code?')) {
                return;
            }

            generateQRCode();
        }

        // Generate QR on page load if it exists
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($referral_data['qr_path'])): ?>
                const referralLink = document.getElementById('referralLink');
                const qrDisplay = document.getElementById('qr-display');

                if (referralLink && qrDisplay && referralLink.value) {
                    new QRCode(qrDisplay, {
                        text: referralLink.value,
                        width: 200,
                        height: 200,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                }
            <?php endif; ?>
        });
    </script>
</body>

</html>