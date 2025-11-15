<?php
// generate_referral.php - Referral Code Generation
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// ✅ Function to generate unique referral code
function generateReferralCode($conn, $user_id) {
    // Format: SALES-[USER_ID]-[RANDOM]
    // Example: SALES-123-A7X9K
    $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
    $code = "SALES-{$user_id}-{$random}";
    
    // Check if code already exists (very unlikely but safe)
    $stmt = $conn->prepare("SELECT id FROM referral_codes WHERE referral_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return generateReferralCode($conn, $user_id); // Recursive retry
    }
    
    $stmt->close();
    return $code;
}

// ✅ Check if user already has an active referral code
$stmt = $conn->prepare("SELECT referral_code, qr_code_path, total_scans, total_conversions, total_revenue, created_at FROM referral_codes WHERE user_id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($existing_code, $qr_path, $scans, $conversions, $revenue, $created);
if ($stmt->fetch()) {
    $referral_data = [
        'code' => $existing_code,
        'qr_path' => $qr_path,
        'scans' => $scans,
        'conversions' => $conversions,
        'revenue' => $revenue,
        'created' => $created
    ];
}
$stmt->close();

// ✅ Handle form submission to generate new code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {
    
    // Check if user already has active code
    if ($referral_data !== null) {
        $error = "You already have an active referral code!";
    } else {
        // Generate new code
        $referral_code = generateReferralCode($conn, $user_id);
        
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO referral_codes (user_id, referral_code, is_active) VALUES (?, ?, 1)");
        $stmt->bind_param("is", $user_id, $referral_code);
        
        if ($stmt->execute()) {
            $message = "Referral code generated successfully!";
            
            // Refresh data
            $referral_data = [
                'code' => $referral_code,
                'qr_path' => null,
                'scans' => 0,
                'conversions' => 0,
                'revenue' => 0.00,
                'created' => date('Y-m-d H:i:s')
            ];
            
        } else {
            $error = "Failed to generate referral code. Please try again.";
        }
        $stmt->close();
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

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header - Mobile Optimized -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
            <div class="py-3 sm:py-4">
                <!-- Mobile Layout -->
                <div class="flex flex-col space-y-3 sm:hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-2 rounded-lg">
                                <i class="fas fa-gift text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold text-gray-900">My Referral</h1>
                                <p class="text-xs text-gray-600">Generate & track</p>
                            </div>
                        </div>
                        <div class="w-10 h-10 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                            <span class="text-white font-bold text-sm">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-1 text-gray-700">
                                <i class="fas fa-user text-primary-600"></i>
                                <span class="font-medium truncate max-w-[120px]"><?php echo htmlspecialchars($fullname); ?></span>
                            </div>
                            <div class="flex items-center space-x-1 text-gray-600">
                                <i class="fas fa-shield-alt"></i>
                                <span><?php echo htmlspecialchars(ucfirst($user_level)); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Layout -->
                <div class="hidden sm:flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-3 rounded-lg">
                            <i class="fas fa-gift text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">My Referral Code</h1>
                            <p class="text-gray-600 mt-1">Generate and track your referral performance</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-900">
                                <i class="fas fa-user text-primary-600 mr-1"></i>
                                <?php echo htmlspecialchars($fullname); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-shield-alt mr-1"></i>
                                <?php echo htmlspecialchars(ucfirst($user_level)); ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?php echo date('M j, Y g:i A'); ?>
                            </div>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                            <span class="text-white font-bold text-lg">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
            
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-4 sm:px-6 py-3 sm:py-4">
                    <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-star mr-2 sm:mr-3"></i>
                        Your Active Referral Code
                    </h2>
                </div>
                
                <div class="p-4 sm:p-6">
                    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-300 rounded-lg p-6 mb-6 text-center">
                        <div class="text-sm text-purple-700 font-medium mb-2">YOUR REFERRAL CODE</div>
                        <div class="text-4xl font-bold text-purple-900 tracking-wider font-mono mb-4">
                            <?php echo htmlspecialchars($referral_data['code']); ?>
                        </div>
                        <button onclick="copyCode()" class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg transition-colors duration-200 inline-flex items-center space-x-2">
                            <i class="fas fa-copy"></i>
                            <span>Copy Code</span>
                        </button>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-link mr-1"></i>Referral Link
                        </label>
                        <div class="flex gap-2">
                            <input type="text" id="referralLink" readonly
                                value="<?php 
                                    $base_url = "https://yourwebsite.com/shop"; // Change this to your actual website
                                    echo htmlspecialchars($base_url . "?ref=" . $referral_data['code']); 
                                ?>"
                                class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm font-mono">
                            <button onclick="copyLink()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                                <i class="fas fa-link"></i>
                                <span>Copy</span>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Share this link with customers to track your referrals</p>
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-blue-600 mb-1">
                                <?php echo number_format($referral_data['scans']); ?>
                            </div>
                            <div class="text-sm text-blue-700 font-medium">Total Scans</div>
                            <div class="text-xs text-blue-600 mt-1">
                                <i class="fas fa-qrcode mr-1"></i>Link clicks
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-green-600 mb-1">
                                <?php echo number_format($referral_data['conversions']); ?>
                            </div>
                            <div class="text-sm text-green-700 font-medium">Conversions</div>
                            <div class="text-xs text-green-600 mt-1">
                                <i class="fas fa-shopping-cart mr-1"></i>Completed purchases
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-purple-600 mb-1">
                                ₱<?php echo number_format($referral_data['revenue'], 2); ?>
                            </div>
                            <div class="text-sm text-purple-700 font-medium">Total Revenue</div>
                            <div class="text-xs text-purple-600 mt-1">
                                <i class="fas fa-coins mr-1"></i>From referrals
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-calendar-check mr-2 text-gray-500"></i>
                                <span>Created on <strong><?php echo date('F j, Y', strtotime($referral_data['created'])); ?></strong></span>
                            </div>
                            <div class="flex items-center text-green-600">
                                <i class="fas fa-check-circle mr-1"></i>
                                <span class="font-medium">Active</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
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

    <script>
        function copyCode() {
            const code = '<?php echo $referral_data['code'] ?? ''; ?>';
            navigator.clipboard.writeText(code).then(() => {
                alert('✓ Referral code copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }

        function copyLink() {
            const linkInput = document.getElementById('referralLink');
            linkInput.select();
            navigator.clipboard.writeText(linkInput.value).then(() => {
                alert('✓ Referral link copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }
    </script>
</body>
</html>