<?php
//accountant.php
session_name("nobleadmin");
session_start();

// CRITICAL FIX: Turn off error display for AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Redirect dispatchers to their own dashboard
if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'document_controller') {
    header("Location: accountant_view_orders.php");
    exit();
}

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Add this after: require_role(['accountant', 'superadmin']);
// =============================== 
// PAYPAL CONFIGURATION 
// =============================== 
$api_username = "sb-e9jug45931077_api1.business.example.com";
$api_password = "JA7M5EZ3V8ZDY3L8";
$api_signature = "AATf2rd9rVjFGl2pL2Qc416hwLDOAIUwgGoZOuE1LuBHdGu5B4TOQgqu";
$endpoint = "https://api-3t.sandbox.paypal.com/nvp";
$version = "204.0";

// Enhanced function to resolve current user details from nobleaccount table
function resolve_current_user_details($conn)
{
    // Return cached user details if already resolved
    if (!empty($_SESSION['current_user_details'])) {
        return $_SESSION['current_user_details'];
    }

    if (empty($_SESSION['noble_user'])) {
        return null;
    }

    $loginValue = $_SESSION['noble_user'];
    $userDetails = null;

    // Query nobleaccount table - this is the primary user table based on your schema
    $stmt = $conn->prepare("SELECT id, fullname, email, lvl, status, is_head, last_login FROM nobleaccount WHERE email = ? OR fullname = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $loginValue, $loginValue);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $userDetails = [
                'id' => (int)$row['id'],
                'name' => $row['fullname'],
                'email' => $row['email'],
                'role' => $row['lvl'], // Using 'lvl' from nobleaccount table
                'status' => $row['status'],
                'is_head' => (bool)$row['is_head'],
                'last_login' => $row['last_login'],
                'table' => 'nobleaccount'
            ];
            $stmt->close();
        } else {
            $stmt->close();
        }
    }

    // If still not found, try by just email (in case fullname doesn't match exactly)
    if (!$userDetails) {
        $stmt = $conn->prepare("SELECT id, fullname, email, lvl, status, is_head, last_login FROM nobleaccount WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $loginValue);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $userDetails = [
                    'id' => (int)$row['id'],
                    'name' => $row['fullname'],
                    'email' => $row['email'],
                    'role' => $row['lvl'],
                    'status' => $row['status'],
                    'is_head' => (bool)$row['is_head'],
                    'last_login' => $row['last_login'],
                    'table' => 'nobleaccount'
                ];
                $stmt->close();
            } else {
                $stmt->close();
            }
        }
    }

    // Cache the results and update session
    if ($userDetails) {
        $_SESSION['current_user_details'] = $userDetails;
        $_SESSION['user_id'] = $userDetails['id'];

        // Update last_activity in database
        $update_stmt = $conn->prepare("UPDATE nobleaccount SET last_activity = NOW() WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param('i', $userDetails['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }

    return $userDetails;
}

// Get current user details
$current_user = resolve_current_user_details($conn);
if (!$current_user) {
    $_SESSION['error'] = "Unable to identify current user. Please login again.";
    header("Location: ../../loginpage/index.php");
    exit();
}

function getPayPalTransactions($days = 30)
{
    global $api_username, $api_password, $api_signature, $endpoint, $version;

    $startDate = date("Y-m-d", strtotime("-$days days")) . "T00:00:00Z";

    $params = [
        "METHOD" => "TransactionSearch",
        "USER" => $api_username,
        "PWD" => $api_password,
        "SIGNATURE" => $api_signature,
        "VERSION" => $version,
        "STARTDATE" => $startDate,
        "ENDDATE" => date("Y-m-d") . "T23:59:59Z",
    ];

    $request = http_build_query($params);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if (!$response) {
        return false;
    }
    curl_close($ch);

    parse_str($response, $parsed_response);
    return $parsed_response;
}

$current_user_id = $current_user['id'];

// Initialize messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// FIXED: Handle AJAX requests for order verification with proper error handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean output buffer to prevent any HTML from contaminating JSON response
    if (ob_get_level()) {
        ob_clean();
    }
    
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'verify_payment') {
            $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

            if (!$order_id || $order_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid order ID provided.']);
                exit();
            }

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Get order details for notification and the total amount
                $order_query = $conn->prepare("SELECT user_id, customer_name, total, payment_status FROM orders WHERE id = ? LIMIT 1");
                if (!$order_query) {
                    throw new Exception("Database prepare failed");
                }
                
                $order_query->bind_param("i", $order_id);
                $order_query->execute();
                $order_result = $order_query->get_result();
                $order_data = $order_result->fetch_assoc();
                $order_query->close();

                if (!$order_data) {
                    throw new Exception("Order not found");
                }

                if ($order_data['payment_status'] === 'verified') {
                    throw new Exception("Order is already verified");
                }

                $total_amount = isset($order_data['total']) ? (float)$order_data['total'] : 0.00;

                // Update order with verification details and set final_total to the order's total
                $stmt = $conn->prepare("UPDATE orders SET payment_status = 'verified', status = 'Ongoing', confirmed_at = CURRENT_TIMESTAMP, verified_by = ?, final_total = ? WHERE id = ?");
                if (!$stmt) {
                    throw new Exception("Database prepare failed for update");
                }
                
                $stmt->bind_param("idi", $current_user_id, $total_amount, $order_id);
                $stmt->execute();
                $stmt->close();

                // Send notification to customer
                if (!empty($order_data) && !empty($order_data['user_id'])) {
                    $notification_message = "Your payment for Order #" . $order_id . " has been verified and confirmed by " . $current_user['name'] . ". Amount: ₱" . number_format($total_amount, 2);
                    $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, created_at) VALUES (?, ?, 'payment_verified', ?, NOW())");
                    if ($notification_stmt) {
                        $notification_stmt->bind_param("iis", $order_data['user_id'], $current_user_id, $notification_message);
                        $notification_stmt->execute();
                        $notification_stmt->close();
                    }
                }

                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'verified_by' => $current_user['name']
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            
        } elseif ($_POST['action'] === 'reject_payment') {
            $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
            $rejection_reason = trim($_POST['rejection_reason'] ?? '');

            if (!$order_id || $order_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid order ID provided.']);
                exit();
            }

            if ($rejection_reason === '') {
                echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
                exit();
            }

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Get order details for notification
                $order_query = $conn->prepare("SELECT user_id, customer_name, total, payment_status FROM orders WHERE id = ? LIMIT 1");
                if (!$order_query) {
                    throw new Exception("Database prepare failed");
                }
                
                $order_query->bind_param("i", $order_id);
                $order_query->execute();
                $order_result = $order_query->get_result();
                $order_data = $order_result->fetch_assoc();
                $order_query->close();

                if (!$order_data) {
                    throw new Exception("Order not found");
                }

                if ($order_data['payment_status'] === 'rejected') {
                    throw new Exception("Order is already rejected");
                }

                $stmt = $conn->prepare("UPDATE orders SET payment_status = 'rejected', rejection_reason = ?, rejection_date = CURRENT_TIMESTAMP, rejected_at = CURRENT_TIMESTAMP, rejected_by = ? WHERE id = ?");
                if (!$stmt) {
                    throw new Exception("Database prepare failed for rejection");
                }
                
                $stmt->bind_param("sii", $rejection_reason, $current_user_id, $order_id);
                $stmt->execute();
                $stmt->close();

                // Send notification to customer
                if (!empty($order_data) && !empty($order_data['user_id'])) {
                    $notification_message = "Your payment for Order #" . $order_id . " has been rejected by " . $current_user['name'] . ". Reason: " . $rejection_reason . ". Please submit a new payment screenshot.";
                    $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, message, created_at) VALUES (?, ?, 'payment_rejected', ?, NOW())");
                    if ($notification_stmt) {
                        $notification_stmt->bind_param("iis", $order_data['user_id'], $current_user_id, $notification_message);
                        $notification_stmt->execute();
                        $notification_stmt->close();
                    }
                }

                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Payment rejected successfully',
                    'rejected_by' => $current_user['name']
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }

    exit();
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'pending';

// Get summary statistics
$pending_payments = 0;
$verified_today = 0;
$total_revenue_today = 0;
$rejected_payments = 0;

// Get payment method breakdown
$paymongo_pending = 0;
$qr_pending = 0;
$bank_pending = 0;
$paypal_pending = 0;

$result = $conn->query("SELECT mode_payment, COUNT(*) as count FROM orders WHERE payment_status = 'pending' GROUP BY mode_payment");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        switch ($row['mode_payment']) {
            case 'PayMongo':
                $paymongo_pending = $row['count'];
                break;
            case 'QR Payment':
                $qr_pending = $row['count'];
                break;
            case 'Bank Transfer':
                $bank_pending = $row['count'];
                break;
            case 'PayPal':
                $paypal_pending = $row['count'];
                break;
        }
    }
}

$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'pending'");
if ($result) {
    $pending_payments = $result->fetch_assoc()['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($result) {
    $verified_today = $result->fetch_assoc()['count'] ?? 0;
}

$result = $conn->query("SELECT COALESCE(SUM(final_total), 0) as total FROM orders WHERE payment_status = 'verified' AND DATE(confirmed_at) = CURDATE()");
if ($result) {
    $total_revenue_today = $result->fetch_assoc()['total'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'rejected'");
if ($result) {
    $rejected_payments = $result->fetch_assoc()['count'] ?? 0;
}

// Fetch PayPal transactions
$paypal_transactions = getPayPalTransactions(30);
$paypal_revenue_today = 0;
$paypal_transaction_count = 0;

if ($paypal_transactions) {
    $i = 0;
    while (isset($paypal_transactions["L_TRANSACTIONID$i"])) {
        $amt = $paypal_transactions["L_AMT$i"] ?? "0.00";
        $timestamp = $paypal_transactions["L_TIMESTAMP$i"] ?? "";

        if ($timestamp && date('Y-m-d', strtotime($timestamp)) === date('Y-m-d')) {
            $paypal_revenue_today += floatval($amt);
        }
        $paypal_transaction_count++;
        $i++;
    }
}

// Get orders based on filter - UPDATED TO USE nobleaccount TABLE WITH PAYMENT METHOD FILTERING
$orders_result = null;
$where_condition = "";
$payment_method_filter = isset($_GET['method']) ? $_GET['method'] : null;
$bank_type_filter = isset($_GET['bank_type']) ? $_GET['bank_type'] : null;
$qr_type_filter = isset($_GET['qr_type']) ? $_GET['qr_type'] : null;

switch ($filter) {
    case 'pending':
        $where_condition = "WHERE o.payment_status = 'pending' AND (
            o.payment_screenshot IS NOT NULL 
            OR o.mode_payment = 'PayPal' 
            OR o.mode_payment = 'PayMongo'
        )";
        
        // Add payment method filtering
        if ($payment_method_filter) {
            switch ($payment_method_filter) {
                case 'paymongo':
                    $where_condition .= " AND o.mode_payment = 'PayMongo'";
                    break;
                case 'qr':
                    $where_condition .= " AND o.mode_payment = 'QR Payment'";
                    // Add QR type filtering if specified
                    if ($qr_type_filter) {
                        $where_condition .= " AND o.bank_type = '" . $conn->real_escape_string($qr_type_filter) . "'";
                    }
                    break;
                case 'bank':
                    $where_condition .= " AND o.mode_payment = 'Bank Transfer'";
                    // Add bank type filtering if specified
                    if ($bank_type_filter) {
                        $where_condition .= " AND o.bank_type = '" . $conn->real_escape_string($bank_type_filter) . "'";
                    }
                    break;
                case 'paypal':
                    $where_condition .= " AND o.mode_payment = 'PayPal'";
                    break;
            }
        }
        break;
    case 'verified':
        $where_condition = "WHERE o.payment_status = 'verified'";
        
        // Add payment method filtering for verified
        if ($payment_method_filter) {
            switch ($payment_method_filter) {
                case 'paymongo':
                    $where_condition .= " AND o.mode_payment = 'PayMongo'";
                    break;
                case 'qr':
                    $where_condition .= " AND o.mode_payment = 'QR Payment'";
                    if ($qr_type_filter) {
                        $where_condition .= " AND o.bank_type = '" . $conn->real_escape_string($qr_type_filter) . "'";
                    }
                    break;
                case 'bank':
                    $where_condition .= " AND o.mode_payment = 'Bank Transfer'";
                    if ($bank_type_filter) {
                        $where_condition .= " AND o.bank_type = '" . $conn->real_escape_string($bank_type_filter) . "'";
                    }
                    break;
                case 'paypal':
                    $where_condition .= " AND o.mode_payment = 'PayPal'";
                    break;
            }
        }
        break;
    case 'rejected':
        $where_condition = "WHERE o.payment_status = 'rejected'";
        
        // Add payment method filtering for rejected
        if ($payment_method_filter) {
            switch ($payment_method_filter) {
                case 'paymongo':
                    $where_condition .= " AND o.mode_payment = 'PayMongo'";
                    break;
                case 'qr':
                    $where_condition .= " AND o.mode_payment = 'QR Payment'";
                    if ($qr_type_filter) {
                        $where_condition .= " AND o.bank_type = '" . $conn->real_escape_string($qr_type_filter) . "'";
                    }
                    break;
                case 'bank':
                    $where_condition .= " AND o.mode_payment = 'Bank Transfer'";
                    if ($bank_type_filter) {
                        $where_condition .= " AND o.bank_type = '" . $conn->real_escape_string($bank_type_filter) . "'";
                    }
                    break;
                case 'paypal':
                    $where_condition .= " AND o.mode_payment = 'PayPal'";
                    break;
            }
        }
        break;
    case 'paypal':
        // This will be handled separately below
        break;
    case 'paymongo':
        // This will be handled separately below
        break;
    default:
        $where_condition = "WHERE o.payment_screenshot IS NOT NULL OR o.mode_payment = 'PayPal'";
        break;
}

$orders_query = "
    SELECT o.*, 
           vb.fullname as verified_by_name,
           rb.fullname as rejected_by_name
    FROM orders o 
    LEFT JOIN nobleaccount vb ON o.verified_by = vb.id
    LEFT JOIN nobleaccount rb ON o.rejected_by = rb.id
    $where_condition
    ORDER BY 
        CASE o.payment_status 
            WHEN 'pending' THEN 1 
            WHEN 'verified' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        o.created_at DESC 
    LIMIT 100
";

$orders_result = $conn->query($orders_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FIXED: Updated Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
                        'noble-orange-light': '#fb923c',
                        'noble-orange-dark': '#ea580c',
                    }
                }
            }
        }
    </script>
    <style>
    .order-row-clickable {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .order-row-clickable:hover {
        background-color: #f3f4f6 !important;
        transform: scale(1.01);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .order-row-clickable:active {
        transform: scale(0.99);
    }
</style>
</head>

<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <header class="bg-white">
        <div class="w-full px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-noble-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-calculator text-white text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Accountant Dashboard</h1>
                        <p class="text-sm text-gray-600">Welcome back, <?php echo htmlspecialchars($current_user['name']); ?></p>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="w-full px-6 py-8">
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div id="successAlert" class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
                <button onclick="document.getElementById('successAlert').style.display='none'" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div id="errorAlert" class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <button onclick="document.getElementById('errorAlert').style.display='none'" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>


        <!-- Filter Tabs -->
<div class="bg-white rounded-xl shadow-sm mb-6">
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8 px-6 overflow-x-auto" aria-label="Tabs">
            <a href="?filter=pending" class="<?php echo $filter === 'pending' && !isset($_GET['method']) ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                <i class="fas fa-clock mr-2"></i>
                Pending (<?php echo number_format($pending_payments); ?>)
            </a>
            <a href="?filter=verified" class="<?php echo $filter === 'verified' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                Verified
            </a>
            <a href="?filter=rejected" class="<?php echo $filter === 'rejected' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                <i class="fas fa-times-circle mr-2"></i>
                Rejected (<?php echo number_format($rejected_payments); ?>)
            </a>
            <a href="?filter=all" class="<?php echo $filter === 'all' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                <i class="fas fa-list mr-2"></i>
                All
            </a>
            <a href="?filter=paypal" class="<?php echo $filter === 'paypal' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
    <i class="fab fa-paypal mr-2"></i>
    PayPal History
</a>
<a href="?filter=paymongo" class="<?php echo $filter === 'paymongo' ? 'border-noble-orange text-noble-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
    <i class="fas fa-mobile-alt mr-2"></i>
    PayMongo History
</a>
        </nav>
    </div>
</div>

<!-- Payment Method Filter Buttons (Shows only when Pending is selected) -->
<?php if ($filter === 'pending'): ?>
    <div class="bg-white rounded-xl shadow-sm mb-6 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Filter by Payment Method:</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- PayMongo Button -->
            <a href="?filter=pending&method=paymongo" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'paymongo') ? 'bg-green-500 text-white border-green-600' : 'bg-white text-gray-700 border-gray-300 hover:border-green-500 hover:bg-green-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fas fa-mobile-alt text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'paymongo') ? 'text-white' : 'text-green-600'; ?>"></i>
                <div class="font-semibold text-sm">PayMongo</div>
                <div class="text-xs mt-1 opacity-75"><?php echo $paymongo_pending; ?> pending</div>
            </a>
            
            <!-- QR Payment Button -->
            <a href="?filter=pending&method=qr" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'qr') ? 'bg-indigo-500 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-500 hover:bg-indigo-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fas fa-qrcode text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'qr') ? 'text-white' : 'text-indigo-600'; ?>"></i>
                <div class="font-semibold text-sm">QR Payment</div>
                <div class="text-xs mt-1 opacity-75"><?php echo $qr_pending; ?> pending</div>
            </a>
            
            <!-- Bank Transfer Button -->
            <a href="?filter=pending&method=bank" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'bank') ? 'bg-purple-500 text-white border-purple-600' : 'bg-white text-gray-700 border-gray-300 hover:border-purple-500 hover:bg-purple-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fas fa-university text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'bank') ? 'text-white' : 'text-purple-600'; ?>"></i>
                <div class="font-semibold text-sm">Bank Transfer</div>
                <div class="text-xs mt-1 opacity-75"><?php echo $bank_pending; ?> pending</div>
            </a>
            
            <!-- PayPal Button -->
            <a href="?filter=pending&method=paypal" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'paypal') ? 'bg-blue-500 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:border-blue-500 hover:bg-blue-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fab fa-paypal text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'paypal') ? 'text-white' : 'text-blue-600'; ?>"></i>
                <div class="font-semibold text-sm">PayPal</div>
                <div class="text-xs mt-1 opacity-75"><?php echo $paypal_pending; ?> pending</div>
            </a>
        </div>
        
        <!-- "Show All" button if a method is selected -->
        <?php if (isset($_GET['method'])): ?>
            <div class="mt-4 text-center">
                <a href="?filter=pending" 
                   class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm font-medium">
                    <i class="fas fa-times mr-2"></i>
                    Clear Filter - Show All Pending
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Bank Type Sub-Filter (shown only when Bank Transfer is selected) -->
<?php if ($filter === 'pending' && isset($_GET['method']) && $_GET['method'] === 'bank'): ?>
    <?php
    // Get available bank types for pending orders
    $bank_types_query = "SELECT DISTINCT bank_type, COUNT(*) as count 
                         FROM orders 
                         WHERE payment_status = 'pending' 
                         AND mode_payment = 'Bank Transfer' 
                         AND bank_type IS NOT NULL 
                         GROUP BY bank_type";
    $bank_types_result = $conn->query($bank_types_query);
    ?>
    
    <div class="bg-white rounded-xl shadow-sm mb-6 p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">
            <i class="fas fa-filter mr-2 text-purple-600"></i>
            Filter by Specific Bank:
        </h3>
        <div class="flex flex-wrap gap-2">
            <a href="?filter=pending&method=bank" 
               class="<?php echo !isset($_GET['bank_type']) ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-university mr-1"></i>
                All Banks
            </a>
            <?php if ($bank_types_result && $bank_types_result->num_rows > 0): ?>
                <?php while ($bank_type_row = $bank_types_result->fetch_assoc()): ?>
                    <a href="?filter=pending&method=bank&bank_type=<?php echo urlencode($bank_type_row['bank_type']); ?>" 
                       class="<?php echo (isset($_GET['bank_type']) && $_GET['bank_type'] === $bank_type_row['bank_type']) ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        <?php echo htmlspecialchars($bank_type_row['bank_type']); ?>
                        <span class="ml-1 text-xs opacity-75">(<?php echo $bank_type_row['count']; ?>)</span>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- QR Payment Sub-Filter (shown only when QR Payment is selected) -->
<?php if ($filter === 'pending' && isset($_GET['method']) && $_GET['method'] === 'qr'): ?>
    <?php
    // Get available QR payment methods
    $qr_types_query = "SELECT DISTINCT 
                            REPLACE(o.bank_type, 'QR_', '') as qr_id,
                            pqc.payment_method,
                            COUNT(*) as count 
                       FROM orders o
                       LEFT JOIN payment_qr_codes pqc ON REPLACE(o.bank_type, 'QR_', '') = pqc.id
                       WHERE o.payment_status = 'pending' 
                       AND o.mode_payment = 'QR Payment' 
                       AND o.bank_type IS NOT NULL
                       AND o.bank_type LIKE 'QR_%'
                       GROUP BY o.bank_type, pqc.payment_method";
    $qr_types_result = $conn->query($qr_types_query);
    ?>
    
    <div class="bg-white rounded-xl shadow-sm mb-6 p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">
            <i class="fas fa-filter mr-2 text-indigo-600"></i>
            Filter by QR Payment Method:
        </h3>
        <div class="flex flex-wrap gap-2">
            <a href="?filter=pending&method=qr" 
               class="<?php echo !isset($_GET['qr_type']) ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-qrcode mr-1"></i>
                All QR Methods
            </a>
            <?php if ($qr_types_result && $qr_types_result->num_rows > 0): ?>
                <?php while ($qr_row = $qr_types_result->fetch_assoc()): ?>
                    <a href="?filter=pending&method=qr&qr_type=QR_<?php echo urlencode($qr_row['qr_id']); ?>" 
                       class="<?php echo (isset($_GET['qr_type']) && $_GET['qr_type'] === 'QR_'.$qr_row['qr_id']) ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        <?php echo htmlspecialchars($qr_row['payment_method'] ?: 'QR Payment'); ?>
                        <span class="ml-1 text-xs opacity-75">(<?php echo $qr_row['count']; ?>)</span>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Payment Method Filter for Verified Tab -->
<?php if ($filter === 'verified'): ?>
    <div class="bg-white rounded-xl shadow-sm mb-6 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Filter Verified Payments by Method:</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- PayMongo Button -->
            <a href="?filter=verified&method=paymongo" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'paymongo') ? 'bg-green-500 text-white border-green-600' : 'bg-white text-gray-700 border-gray-300 hover:border-green-500 hover:bg-green-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fas fa-mobile-alt text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'paymongo') ? 'text-white' : 'text-green-600'; ?>"></i>
                <div class="font-semibold text-sm">PayMongo</div>
            </a>
            
            <!-- QR Payment Button -->
            <a href="?filter=verified&method=qr" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'qr') ? 'bg-indigo-500 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-500 hover:bg-indigo-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fas fa-qrcode text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'qr') ? 'text-white' : 'text-indigo-600'; ?>"></i>
                <div class="font-semibold text-sm">QR Payment</div>
            </a>
            
            <!-- Bank Transfer Button -->
            <a href="?filter=verified&method=bank" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'bank') ? 'bg-purple-500 text-white border-purple-600' : 'bg-white text-gray-700 border-gray-300 hover:border-purple-500 hover:bg-purple-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fas fa-university text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'bank') ? 'text-white' : 'text-purple-600'; ?>"></i>
                <div class="font-semibold text-sm">Bank Transfer</div>
            </a>
            
            <!-- PayPal Button -->
            <a href="?filter=verified&method=paypal" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'paypal') ? 'bg-blue-500 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:border-blue-500 hover:bg-blue-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fab fa-paypal text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'paypal') ? 'text-white' : 'text-blue-600'; ?>"></i>
                <div class="font-semibold text-sm">PayPal</div>
            </a>
        </div>
        
        <?php if (isset($_GET['method'])): ?>
            <div class="mt-4 text-center">
                <a href="?filter=verified" 
                   class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm font-medium">
                    <i class="fas fa-times mr-2"></i>
                    Clear Filter - Show All Verified
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bank Type Sub-Filter for Verified -->
    <?php if (isset($_GET['method']) && $_GET['method'] === 'bank'): ?>
        <?php
        $bank_types_verified = "SELECT DISTINCT bank_type, COUNT(*) as count 
                                FROM orders 
                                WHERE payment_status = 'verified' 
                                AND mode_payment = 'Bank Transfer' 
                                AND bank_type IS NOT NULL 
                                GROUP BY bank_type";
        $bank_types_verified_result = $conn->query($bank_types_verified);
        ?>
        
        <div class="bg-white rounded-xl shadow-sm mb-6 p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                <i class="fas fa-filter mr-2 text-purple-600"></i>
                Filter by Specific Bank:
            </h3>
            <div class="flex flex-wrap gap-2">
                <a href="?filter=verified&method=bank" 
                   class="<?php echo !isset($_GET['bank_type']) ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-university mr-1"></i>
                    All Banks
                </a>
                <?php if ($bank_types_verified_result && $bank_types_verified_result->num_rows > 0): ?>
                    <?php while ($bank_row = $bank_types_verified_result->fetch_assoc()): ?>
                        <a href="?filter=verified&method=bank&bank_type=<?php echo urlencode($bank_row['bank_type']); ?>" 
                           class="<?php echo (isset($_GET['bank_type']) && $_GET['bank_type'] === $bank_row['bank_type']) ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <?php echo htmlspecialchars($bank_row['bank_type']); ?>
                            <span class="ml-1 text-xs opacity-75">(<?php echo $bank_row['count']; ?>)</span>
                        </a>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- QR Type Sub-Filter for Verified -->
    <?php if (isset($_GET['method']) && $_GET['method'] === 'qr'): ?>
        <?php
        $qr_types_verified = "SELECT DISTINCT 
                                REPLACE(o.bank_type, 'QR_', '') as qr_id,
                                pqc.payment_method,
                                COUNT(*) as count 
                            FROM orders o
                            LEFT JOIN payment_qr_codes pqc ON REPLACE(o.bank_type, 'QR_', '') = pqc.id
                            WHERE o.payment_status = 'verified' 
                            AND o.mode_payment = 'QR Payment' 
                            AND o.bank_type IS NOT NULL
                            AND o.bank_type LIKE 'QR_%'
                            GROUP BY o.bank_type, pqc.payment_method";
        $qr_types_verified_result = $conn->query($qr_types_verified);
        ?>
        
        <div class="bg-white rounded-xl shadow-sm mb-6 p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                <i class="fas fa-filter mr-2 text-indigo-600"></i>
                Filter by QR Payment Method:
            </h3>
            <div class="flex flex-wrap gap-2">
                <a href="?filter=verified&method=qr" 
                   class="<?php echo !isset($_GET['qr_type']) ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-qrcode mr-1"></i>
                    All QR Methods
                </a>
                <?php if ($qr_types_verified_result && $qr_types_verified_result->num_rows > 0): ?>
                    <?php while ($qr_row = $qr_types_verified_result->fetch_assoc()): ?>
                        <a href="?filter=verified&method=qr&qr_type=QR_<?php echo urlencode($qr_row['qr_id']); ?>" 
                           class="<?php echo (isset($_GET['qr_type']) && $_GET['qr_type'] === 'QR_'.$qr_row['qr_id']) ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <?php echo htmlspecialchars($qr_row['payment_method'] ?: 'QR Payment'); ?>
                            <span class="ml-1 text-xs opacity-75">(<?php echo $qr_row['count']; ?>)</span>
                        </a>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Payment Method Filter for Rejected Tab -->
<?php if ($filter === 'rejected'): ?>
    <div class="bg-white rounded-xl shadow-sm mb-6 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Filter Rejected Payments by Method:</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- PayMongo Button -->
            <a href="?filter=rejected&method=paymongo" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'paymongo') ? 'bg-green-500 text-white border-green-600' : 'bg-white text-gray-700 border-gray-300 hover:border-green-500 hover:bg-green-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fas fa-mobile-alt text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'paymongo') ? 'text-white' : 'text-green-600'; ?>"></i>
                <div class="font-semibold text-sm">PayMongo</div>
            </a>
            
            <!-- QR Payment Button -->
            <a href="?filter=rejected&method=qr" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'qr') ? 'bg-indigo-500 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-500 hover:bg-indigo-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fas fa-qrcode text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'qr') ? 'text-white' : 'text-indigo-600'; ?>"></i>
                <div class="font-semibold text-sm">QR Payment</div>
            </a>
            
            <!-- Bank Transfer Button -->
            <a href="?filter=rejected&method=bank" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'bank') ? 'bg-purple-500 text-white border-purple-600' : 'bg-white text-gray-700 border-gray-300 hover:border-purple-500 hover:bg-purple-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fas fa-university text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'bank') ? 'text-white' : 'text-purple-600'; ?>"></i>
                <div class="font-semibold text-sm">Bank Transfer</div>
            </a>
            
            <!-- PayPal Button -->
            <a href="?filter=rejected&method=paypal" 
               class="<?php echo (isset($_GET['method']) && $_GET['method'] === 'paypal') ? 'bg-blue-500 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:border-blue-500 hover:bg-blue-50'; ?> border-2 rounded-lg p-4 text-center transition-all transform hover:scale-105 shadow-sm">
                <i class="fab fa-paypal text-3xl mb-2 <?php echo (isset($_GET['method']) && $_GET['method'] === 'paypal') ? 'text-white' : 'text-blue-600'; ?>"></i>
                <div class="font-semibold text-sm">PayPal</div>
            </a>
        </div>
        
        <?php if (isset($_GET['method'])): ?>
            <div class="mt-4 text-center">
                <a href="?filter=rejected" 
                   class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm font-medium">
                    <i class="fas fa-times mr-2"></i>
                    Clear Filter - Show All Rejected
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bank Type Sub-Filter for Rejected -->
    <?php if (isset($_GET['method']) && $_GET['method'] === 'bank'): ?>
        <?php
        $bank_types_rejected = "SELECT DISTINCT bank_type, COUNT(*) as count 
                                FROM orders 
                                WHERE payment_status = 'rejected' 
                                AND mode_payment = 'Bank Transfer' 
                                AND bank_type IS NOT NULL 
                                GROUP BY bank_type";
        $bank_types_rejected_result = $conn->query($bank_types_rejected);
        ?>
        
        <div class="bg-white rounded-xl shadow-sm mb-6 p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                <i class="fas fa-filter mr-2 text-purple-600"></i>
                Filter by Specific Bank:
            </h3>
            <div class="flex flex-wrap gap-2">
                <a href="?filter=rejected&method=bank" 
                   class="<?php echo !isset($_GET['bank_type']) ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-university mr-1"></i>
                    All Banks
                </a>
                <?php if ($bank_types_rejected_result && $bank_types_rejected_result->num_rows > 0): ?>
                    <?php while ($bank_row = $bank_types_rejected_result->fetch_assoc()): ?>
                        <a href="?filter=rejected&method=bank&bank_type=<?php echo urlencode($bank_row['bank_type']); ?>" 
                           class="<?php echo (isset($_GET['bank_type']) && $_GET['bank_type'] === $bank_row['bank_type']) ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <?php echo htmlspecialchars($bank_row['bank_type']); ?>
                            <span class="ml-1 text-xs opacity-75">(<?php echo $bank_row['count']; ?>)</span>
                        </a>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- QR Type Sub-Filter for Rejected -->
    <?php if (isset($_GET['method']) && $_GET['method'] === 'qr'): ?>
        <?php
        $qr_types_rejected = "SELECT DISTINCT 
                                REPLACE(o.bank_type, 'QR_', '') as qr_id,
                                pqc.payment_method,
                                COUNT(*) as count 
                            FROM orders o
                            LEFT JOIN payment_qr_codes pqc ON REPLACE(o.bank_type, 'QR_', '') = pqc.id
                            WHERE o.payment_status = 'rejected' 
                            AND o.mode_payment = 'QR Payment' 
                            AND o.bank_type IS NOT NULL
                            AND o.bank_type LIKE 'QR_%'
                            GROUP BY o.bank_type, pqc.payment_method";
        $qr_types_rejected_result = $conn->query($qr_types_rejected);
        ?>
        
        <div class="bg-white rounded-xl shadow-sm mb-6 p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                <i class="fas fa-filter mr-2 text-indigo-600"></i>
                Filter by QR Payment Method:
            </h3>
            <div class="flex flex-wrap gap-2">
                <a href="?filter=rejected&method=qr" 
                   class="<?php echo !isset($_GET['qr_type']) ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-qrcode mr-1"></i>
                    All QR Methods
                </a>
                <?php if ($qr_types_rejected_result && $qr_types_rejected_result->num_rows > 0): ?>
                    <?php while ($qr_row = $qr_types_rejected_result->fetch_assoc()): ?>
                        <a href="?filter=rejected&method=qr&qr_type=QR_<?php echo urlencode($qr_row['qr_id']); ?>" 
                           class="<?php echo (isset($_GET['qr_type']) && $_GET['qr_type'] === 'QR_'.$qr_row['qr_id']) ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <?php echo htmlspecialchars($qr_row['payment_method'] ?: 'QR Payment'); ?>
                            <span class="ml-1 text-xs opacity-75">(<?php echo $qr_row['count']; ?>)</span>
                        </a>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
<!-- Payment Verification Queue Header with Statistics Cards -->
<div class="flex flex-col lg:flex-row gap-6 mb-8">
    <!-- Header Section -->
    <div class="lg:w-1/4  p-6 flex flex-col justify-center">
        <h2 class="text-lg font-semibold text-gray-900">Payment Verification Queue</h2>
        <p class="text-sm text-gray-600 mt-2">Review and verify customer payments</p>
    </div>

    <!-- Statistics Cards -->
    <div class="lg:w-3/4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class=" p-6  transform hover:scale-105 transition-transform">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Pending Payments</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($pending_payments); ?></p>
                    <p class="text-xs text-yellow-600 mt-1">Awaiting review</p>
                </div>
            </div>
        </div>

        <div class="p-6  transform hover:scale-105 transition-transform">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Verified Today</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($verified_today); ?></p>
                    <p class="text-xs text-green-600 mt-1"><?php echo date('M d, Y'); ?></p>
                </div>
            </div>
        </div>

        <div class="p-6  transform hover:scale-105 transition-transform">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-peso-sign text-noble-orange text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Revenue Today</p>
                    <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($total_revenue_today, 2); ?></p>
                    <p class="text-xs text-noble-orange mt-1">Verified payments</p>
                </div>
            </div>
        </div>

        <div class="p-6  transform hover:scale-105 transition-transform">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Rejected</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($rejected_payments); ?></p>
                    <p class="text-xs text-red-600 mt-1">Total rejected</p>
                </div>
            </div>
        </div>
    </div>
</div>
            <?php if ($filter !== 'paypal' && $filter !== 'paymongo'): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                                <?php while ($order = $orders_result->fetch_assoc()): ?>
                                    <tr class="order-row-clickable hover:bg-gray-50 transition-colors" id="order-row-<?php echo $order['id']; ?>" onclick="viewOrderDetails(<?php echo $order['id']; ?>)" title="Click to view order details">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
    <span class="bg-gray-100 px-2 py-1 rounded-md inline-flex items-center">
        <i class="fas fa-eye text-noble-orange mr-2 text-xs"></i>
        #<?php echo $order['id']; ?>
    </span>
</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-blue-600 text-xs font-semibold">
                                                        <?php echo strtoupper(substr($order['customer_name'] ?: 'U', 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $display_amount = $order['total'];
                                            if ($order['payment_status'] === 'verified' && isset($order['final_total']) && $order['final_total'] !== null && $order['final_total'] !== '') {
                                                $display_amount = $order['final_total'];
                                            }
                                            ?>
                                            <div class="text-sm font-semibold text-gray-900">₱<?php echo number_format((float)$display_amount, 2); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
    <div class="text-sm text-gray-900 flex items-center">
        <?php if ($order['mode_payment'] === 'PayPal'): ?>
            <i class="fab fa-paypal text-blue-600 mr-2"></i>
        <?php elseif ($order['mode_payment'] === 'PayMongo'): ?>
            <i class="fas fa-mobile-alt text-green-600 mr-2"></i>
        <?php elseif ($order['mode_payment'] === 'QR Payment'): ?>
            <i class="fas fa-qrcode text-indigo-600 mr-2"></i>
        <?php elseif ($order['mode_payment'] === 'Bank Transfer'): ?>
            <i class="fas fa-university text-purple-600 mr-2"></i>
        <?php endif; ?>
        <?php echo htmlspecialchars($order['mode_payment'] ?: 'N/A'); ?>
    </div>
    <?php if ($order['bank_type']): ?>
        <div class="text-sm text-gray-500">
            <?php 
            // Handle QR Payment display
            if (strpos($order['bank_type'], 'QR_') === 0) {
                $qr_id = str_replace('QR_', '', $order['bank_type']);
                // Fetch QR payment method name
                $qr_stmt = $conn->prepare("SELECT payment_method FROM payment_qr_codes WHERE id = ?");
                $qr_stmt->bind_param("i", $qr_id);
                $qr_stmt->execute();
                $qr_result = $qr_stmt->get_result();
                if ($qr_row = $qr_result->fetch_assoc()) {
                    echo htmlspecialchars($qr_row['payment_method']);
                }
                $qr_stmt->close();
            } else {
                echo htmlspecialchars($order['bank_type']);
            }
            ?>
        </div>
    <?php endif; ?>
</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <code class="text-sm bg-gray-100 px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($order['reference_number'] ?: $order['reference_no'] ?: 'N/A'); ?>
                                            </code>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                'verified' => 'bg-green-100 text-green-800 border-green-200',
                                                'rejected' => 'bg-red-100 text-red-800 border-red-200'
                                            ];
                                            $status = $order['payment_status'];
                                            $status_icons = [
                                                'pending' => 'fas fa-clock',
                                                'verified' => 'fas fa-check-circle',
                                                'rejected' => 'fas fa-times-circle'
                                            ];
                                            ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border <?php echo $status_colors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200'; ?>">
                                                <i class="<?php echo $status_icons[$status] ?? 'fas fa-question'; ?> mr-1"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                            <?php if ($order['payment_status'] === 'rejected' && $order['rejection_reason']): ?>
                                                <div class="text-xs text-red-600 mt-1 cursor-pointer" title="<?php echo htmlspecialchars($order['rejection_reason']); ?>">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Reason: <?php echo htmlspecialchars(substr($order['rejection_reason'], 0, 20)) . (strlen($order['rejection_reason']) > 20 ? '...' : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php
                                                if ($order['payment_status'] === 'verified' && $order['verified_by_name']) {
                                                    echo htmlspecialchars($order['verified_by_name']);
                                                } elseif ($order['payment_status'] === 'rejected' && $order['rejected_by_name']) {
                                                    echo htmlspecialchars($order['rejected_by_name']);
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </div>
                                            <?php if ($order['confirmed_at'] && $order['payment_status'] === 'verified'): ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M d, H:i', strtotime($order['confirmed_at'])); ?>
                                                </div>
                                            <?php elseif ($order['rejected_at'] && $order['payment_status'] === 'rejected'): ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M d, H:i', strtotime($order['rejected_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($order['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" onclick="event.stopPropagation()">
    <?php if ($order['payment_status'] === 'pending'): ?>
        <div class="flex space-x-2">
            <?php if ($order['payment_screenshot']): ?>
                <?php
                $screenshot_path = $order['payment_screenshot'];
                // Improved path handling for both old and new formats
                if ($screenshot_path) {
                    if (strpos($screenshot_path, 'uploads/payment_screenshots/') !== false) {
                        $full_screenshot_path = $screenshot_path;
                    } else {
                        $full_screenshot_path = '../../uploads/payment_screenshots/' . $screenshot_path;
                    }
                } else {
                    $full_screenshot_path = '';
                }
                ?>
                <button onclick="viewPaymentScreenshot('<?php echo htmlspecialchars($full_screenshot_path); ?>', <?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['mode_payment']); ?>')"
                    class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-xs leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-noble-orange">
                    <i class="fas fa-eye mr-1"></i>
                    View
                </button>
            <?php elseif ($order['mode_payment'] === 'PayPal'): ?>
                <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full">
                    <i class="fab fa-paypal mr-1"></i>
                    PayPal Payment
                </span>
            <?php elseif ($order['mode_payment'] === 'PayMongo'): ?>
                <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full">
                    <i class="fas fa-mobile-alt mr-1"></i>
                    PayMongo Paid
                </span>
            <?php endif; ?>
            
            <button onclick="verifyPayment(<?php echo $order['id']; ?>)"
                class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i class="fas fa-check mr-1"></i>
                Verify
            </button>
            <button onclick="showRejectModal(<?php echo $order['id']; ?>)"
                class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                <i class="fas fa-times mr-1"></i>
                Reject
            </button>
        </div>
    <?php elseif ($order['payment_screenshot']): ?>
        <?php
        $screenshot_path = $order['payment_screenshot'];
        if ($screenshot_path) {
            if (strpos($screenshot_path, 'uploads/payment_screenshots/') !== false) {
                $full_screenshot_path = $screenshot_path;
            } else {
                $full_screenshot_path = '../../uploads/payment_screenshots/' . $screenshot_path;
            }
        } else {
            $full_screenshot_path = '';
        }
        ?>
        <button onclick="viewPaymentScreenshot('<?php echo htmlspecialchars($full_screenshot_path); ?>', <?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['mode_payment']); ?>')"
            class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-xs leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-noble-orange">
            <i class="fas fa-eye mr-1"></i>
            View
        </button>
    <?php elseif ($order['mode_payment'] === 'PayPal'): ?>
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full">
            <i class="fab fa-paypal mr-1"></i>
            PayPal Payment
        </span>
    <?php elseif ($order['mode_payment'] === 'PayMongo'): ?>
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full">
            <i class="fas fa-mobile-alt mr-1"></i>
            PayMongo Paid
        </span>
    <?php else: ?>
        <span class="text-xs text-gray-400">No screenshot</span>
    <?php endif; ?>
</td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-1">No orders found</h3>
                                            <p class="text-sm text-gray-500">
                                                <?php if ($filter === 'pending'): ?>
                                                    No pending payments to review at the moment.
                                                <?php elseif ($filter === 'verified'): ?>
                                                    No verified payments found.
                                                <?php elseif ($filter === 'rejected'): ?>
                                                    No rejected payments found.
                                                <?php else: ?>
                                                    No payment records found.
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- PayPal Transactions Table (show only when filter is 'paypal') -->
        <?php if ($filter === 'paypal'): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">PayPal Transaction History</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($paypal_transactions): ?>
                                <?php
                                $i = 0;
                                $has_transactions = false;
                                while (isset($paypal_transactions["L_TRANSACTIONID$i"])):
                                    $has_transactions = true;
                                    $id = $paypal_transactions["L_TRANSACTIONID$i"];
                                    $type = $paypal_transactions["L_TYPE$i"] ?? "N/A";
                                    $status = $paypal_transactions["L_STATUS$i"] ?? "N/A";
                                    $amt = $paypal_transactions["L_AMT$i"] ?? "0.00";
                                    $fee = $paypal_transactions["L_FEEAMT$i"] ?? "0.00";
                                    $time = $paypal_transactions["L_TIMESTAMP$i"] ?? "N/A";

                                    if ($time !== "N/A") {
                                        $timestamp = strtotime($time);
                                        $formatted_time = date("M d, Y h:i A", $timestamp);
                                    } else {
                                        $formatted_time = "N/A";
                                    }

                                    $status_colors = [
                                        'Completed' => 'bg-green-100 text-green-800 border-green-200',
                                        'Pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                        'Failed' => 'bg-red-100 text-red-800 border-red-200'
                                    ];
                                    $status_class = $status_colors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <code class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($id); ?></code>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($type); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            ₱<?php echo htmlspecialchars($amt); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ₱<?php echo htmlspecialchars($fee); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($formatted_time); ?>
                                        </td>
                                    </tr>
                                <?php
                                    $i++;
                                endwhile;

                                if (!$has_transactions):
                                ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <i class="fab fa-paypal text-4xl text-gray-300 mb-4"></i>
                                                <h3 class="text-lg font-medium text-gray-900 mb-1">No PayPal transactions found</h3>
                                                <p class="text-sm text-gray-500">No transactions found in the last 30 days.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-exclamation-triangle text-4xl text-gray-300 mb-4"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-1">Unable to fetch PayPal data</h3>
                                            <p class="text-sm text-gray-500">There was an error connecting to PayPal API.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        <!-- PayMongo Transactions Table (show only when filter is 'paymongo') -->
        <?php if ($filter === 'paymongo'): ?>
            <?php
            // Fetch PayMongo transaction history
            $paymongo_query = "SELECT o.*, 
                                      vb.fullname as verified_by_name
                               FROM orders o 
                               LEFT JOIN nobleaccount vb ON o.verified_by = vb.id
                               WHERE o.mode_payment = 'PayMongo'
                               ORDER BY o.created_at DESC 
                               LIMIT 100";
            $paymongo_result = $conn->query($paymongo_query);
            ?>
            
            <div class="bg-white rounded-xl shadow-sm overflow-hidden mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-mobile-alt text-green-600 mr-2"></i>
                        PayMongo Transaction History
                    </h2>
                    <p class="text-sm text-gray-600">All PayMongo payment transactions</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verified By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($paymongo_result && $paymongo_result->num_rows > 0): ?>
                                <?php while ($transaction = $paymongo_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="bg-gray-100 px-2 py-1 rounded-md text-sm font-medium">#<?php echo $transaction['id']; ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-green-600 text-xs font-semibold">
                                                        <?php echo strtoupper(substr($transaction['customer_name'] ?: 'U', 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($transaction['customer_name'] ?: 'N/A'); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($transaction['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $display_amount = $transaction['total'];
                                            if ($transaction['payment_status'] === 'verified' && isset($transaction['final_total']) && $transaction['final_total'] !== null && $transaction['final_total'] !== '') {
                                                $display_amount = $transaction['final_total'];
                                            }
                                            ?>
                                            <div class="text-sm font-semibold text-gray-900">₱<?php echo number_format((float)$display_amount, 2); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <code class="text-sm bg-gray-100 px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($transaction['reference_number'] ?: $transaction['reference_no'] ?: 'N/A'); ?>
                                            </code>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                'verified' => 'bg-green-100 text-green-800 border-green-200',
                                                'rejected' => 'bg-red-100 text-red-800 border-red-200'
                                            ];
                                            $status = $transaction['payment_status'];
                                            $status_icons = [
                                                'pending' => 'fas fa-clock',
                                                'verified' => 'fas fa-check-circle',
                                                'rejected' => 'fas fa-times-circle'
                                            ];
                                            ?>
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border <?php echo $status_colors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200'; ?>">
                                                <i class="<?php echo $status_icons[$status] ?? 'fas fa-question'; ?> mr-1"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($transaction['verified_by_name'] ?: 'N/A'); ?>
                                            </div>
                                            <?php if ($transaction['confirmed_at']): ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M d, H:i', strtotime($transaction['confirmed_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('M d, Y', strtotime($transaction['created_at'])); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($transaction['created_at'])); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-mobile-alt text-4xl text-gray-300 mb-4"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-1">No PayMongo transactions found</h3>
                                            <p class="text-sm text-gray-500">No transactions found for PayMongo payment method.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Payment Screenshot Modal -->
<div id="screenshotModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between pb-3 border-b">
            <h3 class="text-lg font-bold text-gray-900">
                <span id="modalPaymentType">Payment Screenshot</span> - Order #<span id="modalOrderId"></span>
            </h3>
            <button onclick="closeScreenshotModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="mt-4">
            <div class="flex justify-center">
                <img id="screenshotImage" src="" alt="Payment Screenshot" class="max-w-full max-h-96 object-contain rounded-lg shadow-md">
            </div>
        </div>
    </div>
</div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between pb-3 border-b">
                <h3 class="text-lg font-bold text-gray-900">Reject Payment - Order #<span id="rejectOrderId"></span></h3>
                <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mt-4">
                <label for="rejectionReason" class="block text-sm font-medium text-gray-700 mb-2">
                    Rejection Reason <span class="text-red-500">*</span>
                </label>
                <textarea id="rejectionReason" rows="4"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-noble-orange focus:border-transparent"
                    placeholder="Please provide a detailed reason for rejecting this payment..."
                    maxlength="500"></textarea>
                <div class="text-xs text-gray-500 mt-1">
                    <span id="reasonCharCount">0</span>/500 characters
                </div>
            </div>
            <div class="flex items-center justify-end pt-4 border-t space-x-3">
                <button onclick="closeRejectModal()"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancel
                </button>
                <button onclick="submitRejection()"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                    id="submitRejectBtn">
                    <i class="fas fa-times mr-2"></i>
                    Reject Payment
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 shadow-xl">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-noble-orange"></div>
                <span class="text-gray-700">Processing...</span>
            </div>
        </div>
    </div>

    <!-- FIXED: Improved JavaScript with better error handling -->
    <script>
        let currentOrderId = null;

        function viewPaymentScreenshot(screenshotPath, orderId, paymentMethod) {
    try {
        document.getElementById('modalOrderId').textContent = orderId;
        
        // Update modal title based on payment method
        const modalPaymentType = document.getElementById('modalPaymentType');
        let paymentIcon = '';
        
        if (paymentMethod === 'PayMongo') {
            paymentIcon = '<i class="fas fa-mobile-alt text-green-600 mr-2"></i>';
            modalPaymentType.innerHTML = paymentIcon + 'PayMongo Payment Screenshot';
        } else if (paymentMethod === 'QR Payment') {
            paymentIcon = '<i class="fas fa-qrcode text-indigo-600 mr-2"></i>';
            modalPaymentType.innerHTML = paymentIcon + 'QR Payment Screenshot';
        } else if (paymentMethod === 'Bank Transfer') {
            paymentIcon = '<i class="fas fa-university text-purple-600 mr-2"></i>';
            modalPaymentType.innerHTML = paymentIcon + 'Bank Transfer Screenshot';
        } else {
            modalPaymentType.textContent = 'Payment Screenshot';
        }

        let fullPath;
        // Check if the path already contains the full uploads path
        if (screenshotPath.includes('uploads/payment_screenshots/')) {
            fullPath = screenshotPath;
        } else {
            fullPath = '../../uploads/payment_screenshots/' + screenshotPath;
        }

        const img = document.getElementById('screenshotImage');
        img.onerror = function() {
            showAlert('error', 'Failed to load payment screenshot');
            closeScreenshotModal();
        };
        
        img.src = fullPath;
        document.getElementById('screenshotModal').classList.remove('hidden');
    } catch (error) {
        console.error('Error viewing screenshot:', error);
        showAlert('error', 'Error opening screenshot viewer');
    }
}

        function closeScreenshotModal() {
            document.getElementById('screenshotModal').classList.add('hidden');
        }

        function viewOrderDetails(orderId) {
    // Open order details in a new window/tab
    const width = 1200;
    const height = 800;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    
    window.open(
        'order_details.php?id=' + orderId,
        'OrderDetails',
        `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
    );
}

        function showRejectModal(orderId) {
            currentOrderId = orderId;
            document.getElementById('rejectOrderId').textContent = orderId;
            document.getElementById('rejectionReason').value = '';
            document.getElementById('reasonCharCount').textContent = '0';
            document.getElementById('rejectionModal').classList.remove('hidden');
        }

        function closeRejectModal() {
            document.getElementById('rejectionModal').classList.add('hidden');
            currentOrderId = null;
        }

        // Character count for rejection reason
        document.getElementById('rejectionReason').addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('reasonCharCount').textContent = count;
        });

        // FIXED: Enhanced payment verification with better error handling
        function verifyPayment(orderId) {
            if (!confirm('Are you sure you want to verify this payment? This action cannot be undone.')) {
                return;
            }

            if (!orderId || orderId <= 0) {
                showAlert('error', 'Invalid order ID');
                return;
            }

            showLoading();

            const formData = new FormData();
            formData.append('action', 'verify_payment');
            formData.append('order_id', orderId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Invalid server response format');
                    }
                });
            })
            .then(data => {
                hideLoading();
                if (data.success) {
                    showAlert('success', data.message);
                    updateOrderRow(orderId, 'verified', data.verified_by);
                } else {
                    showAlert('error', data.message || 'Failed to verify payment');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showAlert('error', 'Network error occurred while verifying payment');
            });
        }

        // FIXED: Enhanced rejection submission with better error handling
        function submitRejection() {
            const reason = document.getElementById('rejectionReason').value.trim();

            if (!reason) {
                showAlert('error', 'Please provide a rejection reason');
                return;
            }

            if (!currentOrderId || currentOrderId <= 0) {
                showAlert('error', 'Invalid order ID');
                return;
            }

            const submitBtn = document.getElementById('submitRejectBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Rejecting...';

            const formData = new FormData();
            formData.append('action', 'reject_payment');
            formData.append('order_id', currentOrderId);
            formData.append('rejection_reason', reason);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Invalid server response format');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    updateOrderRow(currentOrderId, 'rejected', data.rejected_by, reason);
                    closeRejectModal();
                } else {
                    showAlert('error', data.message || 'Failed to reject payment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Network error occurred while rejecting payment');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Reject Payment';
            });
        }

        function updateOrderRow(orderId, newStatus, processedBy, rejectionReason = null) {
            const row = document.getElementById('order-row-' + orderId);
            if (!row) return;

            try {
                // Update status cell
                const statusCell = row.cells[5];
                let statusHTML = '';
                let statusClass = '';
                let statusIcon = '';

                switch (newStatus) {
                  case 'verified':
                        statusClass = 'bg-green-100 text-green-800 border-green-200';
                        statusIcon = 'fas fa-check-circle';
                        statusHTML = `<span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border ${statusClass}">
                            <i class="${statusIcon} mr-1"></i>
                            Verified
                        </span>`;
                        break;
                    case 'rejected':
                        statusClass = 'bg-red-100 text-red-800 border-red-200';
                        statusIcon = 'fas fa-times-circle';
                        statusHTML = `<span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full border ${statusClass}">
                            <i class="${statusIcon} mr-1"></i>
                            Rejected
                        </span>`;
                        if (rejectionReason) {
                            statusHTML += `<div class="text-xs text-red-600 mt-1 cursor-pointer" title="${rejectionReason}">
                                <i class="fas fa-info-circle mr-1"></i>
                                Reason: ${rejectionReason.substring(0, 20)}${rejectionReason.length > 20 ? '...' : ''}
                            </div>`;
                        }
                        break;
                }
                statusCell.innerHTML = statusHTML;

                // Update processed by cell
                const processedByCell = row.cells[6];
                const currentTime = new Date().toLocaleString('en-US', {
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
                
                processedByCell.innerHTML = `
                    <div class="text-sm text-gray-900">${processedBy}</div>
                    <div class="text-xs text-gray-500">${currentTime}</div>
                `;

                // Update actions cell - remove action buttons for processed orders
                const actionsCell = row.cells[8];
                if (newStatus === 'verified' || newStatus === 'rejected') {
                    // Keep only the view screenshot button if it exists
                    const viewButton = actionsCell.querySelector('button[onclick*="viewPaymentScreenshot"]');
                    if (viewButton) {
                        actionsCell.innerHTML = '';
                        actionsCell.appendChild(viewButton);
                    } else {
                        actionsCell.innerHTML = '<span class="text-xs text-gray-400">Processed</span>';
                    }
                }

                // Add a subtle animation to highlight the change
                row.classList.add('bg-yellow-50');
                setTimeout(() => {
                    row.classList.remove('bg-yellow-50');
                }, 2000);

            } catch (error) {
                console.error('Error updating order row:', error);
            }
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }

        function showAlert(type, message) {
            // Remove any existing alerts
            const existingAlerts = document.querySelectorAll('.alert-message');
            existingAlerts.forEach(alert => alert.remove());

            const alertColors = {
                'success': 'bg-green-50 border-green-200 text-green-800',
                'error': 'bg-red-50 border-red-200 text-red-800',
                'warning': 'bg-yellow-50 border-yellow-200 text-yellow-800'
            };

            const alertIcons = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-exclamation-triangle',
                'warning': 'fas fa-exclamation-circle'
            };

            const alertHTML = `
                <div class="alert-message mb-6 ${alertColors[type]} border px-4 py-3 rounded-lg flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="${alertIcons[type]} mr-2"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="hover:opacity-75">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            // Insert at the top of main content
            const mainContent = document.querySelector('main');
            mainContent.insertAdjacentHTML('afterbegin', alertHTML);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                const alert = document.querySelector('.alert-message');
                if (alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            }, 5000);
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeScreenshotModal();
                closeRejectModal();
            }
        });

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const screenshotModal = document.getElementById('screenshotModal');
            const rejectionModal = document.getElementById('rejectionModal');
            
            if (event.target === screenshotModal) {
                closeScreenshotModal();
            }
            if (event.target === rejectionModal) {
                closeRejectModal();
            }
        });

        // Auto-refresh page every 5 minutes to show new orders
        let autoRefreshInterval = setInterval(() => {
            // Only refresh if no modals are open
            const modalsOpen = !document.getElementById('screenshotModal').classList.contains('hidden') ||
                              !document.getElementById('rejectionModal').classList.contains('hidden');
            
            if (!modalsOpen) {
                // Check if there are new pending orders by making a quick AJAX call
                fetch(window.location.href + '&check_new=1')
                    .then(response => response.text())
                    .then(data => {
                        // Simple check - you might want to implement a more sophisticated check
                        if (data.includes('new-orders-available')) {
                            showAlert('warning', 'New orders are available. Page will refresh in 5 seconds...');
                            setTimeout(() => {
                                window.location.reload();
                            }, 5000);
                        }
                    })
                    .catch(error => {
                        console.log('Auto-refresh check failed:', error);
                    });
            }
        }, 300000); // 5 minutes

        // Clear interval when page is being unloaded
        window.addEventListener('beforeunload', function() {
            clearInterval(autoRefreshInterval);
        });

        // Initialize tooltips for rejection reasons (if any exist)
        document.addEventListener('DOMContentLoaded', function() {
            const rejectionTooltips = document.querySelectorAll('[title]');
            rejectionTooltips.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    // You could implement a custom tooltip here if needed
                });
            });
        });

        // Prevent form submission on enter key in textarea
        document.getElementById('rejectionReason').addEventListener('keydown', function(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                submitRejection();
            }
        });

        console.log('Accountant Dashboard JavaScript loaded successfully');
        
        </script>

    <!-- Additional Features: Quick Stats Refresh -->
    <script>
        // Quick stats refresh without full page reload
        function refreshStats() {
            fetch('?ajax_stats=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update stat cards
                        document.querySelector('.pending-count').textContent = data.pending_payments;
                        document.querySelector('.verified-count').textContent = data.verified_today;
                        document.querySelector('.revenue-amount').textContent = '₱' + data.total_revenue_today;
                        document.querySelector('.rejected-count').textContent = data.rejected_payments;
                        document.querySelector('.paypal-revenue').textContent = '₱' + data.paypal_revenue_today;
                    }
                })
                .catch(error => console.log('Stats refresh failed:', error));
        }

        // Refresh stats every 2 minutes
        setInterval(refreshStats, 120000);
    </script>


</body>
</html>