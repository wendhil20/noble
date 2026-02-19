<?php
// accountant_commission_release.php
session_name("nobleadmin");
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$_SESSION['last_activity'] = time();
$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];

$message = "";
$error = "";

// Handle release - MARK ORDERS AS CLAIMED
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_payment'])) {
    $claim_id = intval($_POST['claim_id']);
    $notes = $_POST['release_notes'] ?? '';
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get order IDs from this claim
        $get_orders_stmt = $conn->prepare("SELECT order_ids FROM commission_claims WHERE id = ?");
        $get_orders_stmt->bind_param("i", $claim_id);
        $get_orders_stmt->execute();
        $get_orders_stmt->bind_result($order_ids);
        $get_orders_stmt->fetch();
        $get_orders_stmt->close();
        
        if ($order_ids) {
            // Mark all orders as claimed
            $order_ids_array = explode(',', $order_ids);
            $placeholders = implode(',', array_fill(0, count($order_ids_array), '?'));
            
            $mark_claimed_stmt = $conn->prepare("UPDATE orders SET commission_claimed = 1 WHERE id IN ($placeholders)");
            $types = str_repeat('i', count($order_ids_array));
            $mark_claimed_stmt->bind_param($types, ...$order_ids_array);
            $mark_claimed_stmt->execute();
            $mark_claimed_stmt->close();
        }
        
        // Update claim status to released
        $release_stmt = $conn->prepare("
            UPDATE commission_claims 
            SET status = 'released', released_by = ?, released_at = NOW(), notes = ? 
            WHERE id = ? AND status = 'approved'
        ");
        $release_stmt->bind_param("isi", $user_id, $notes, $claim_id);
        $release_stmt->execute();
        $release_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Commission payment released successfully! Orders marked as claimed.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to release payment: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get success message
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Fetch approved claims
$approved_stmt = $conn->prepare("
    SELECT 
        cc.id,
        cc.sales_user_id,
        cc.referral_code,
        cc.commission_amount,
        cc.claim_date,
        cc.approved_at,
        cc.status,
        cc.order_count,
        na.fullname as sales_name,
        na.email as sales_email,
        approver.fullname as approved_by_name
    FROM commission_claims cc
    INNER JOIN nobleaccount na ON cc.sales_user_id = na.id
    LEFT JOIN nobleaccount approver ON cc.approved_by = approver.id
    WHERE cc.status = 'approved'
    ORDER BY cc.approved_at ASC
");
$approved_stmt->execute();
$approved_result = $approved_stmt->get_result();

// Fetch released claims
$released_stmt = $conn->prepare("
    SELECT 
        cc.id,
        cc.sales_user_id,
        cc.referral_code,
        cc.commission_amount,
        cc.released_at,
        cc.notes,
        cc.order_count,
        na.fullname as sales_name,
        releaser.fullname as released_by_name
    FROM commission_claims cc
    INNER JOIN nobleaccount na ON cc.sales_user_id = na.id
    LEFT JOIN nobleaccount releaser ON cc.released_by = releaser.id
    WHERE cc.status = 'released'
    ORDER BY cc.released_at DESC
    LIMIT 50
");
$released_stmt->execute();
$released_result = $released_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Release - Accountant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-money-bill-wave text-blue-600 mr-3"></i>
                Commission Payment Release
            </h1>
            <p class="text-gray-600 mt-2">Release approved commission payments to sales staff</p>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4 mb-6">
                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                <span class="text-green-800"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 mb-6">
                <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                <span class="text-red-800"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Approved Claims -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
            <div class="bg-blue-500 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-clock mr-2"></i>
                    Ready for Release (<?php echo $approved_result->num_rows; ?>)
                </h2>
            </div>
            
            <?php if ($approved_result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Sales Person</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Code</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Orders</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">Amount</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Approved By</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Approved Date</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($claim = $approved_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($claim['sales_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($claim['sales_email']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-mono font-bold text-purple-700"><?php echo htmlspecialchars($claim['referral_code']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">
                                            <?php echo number_format($claim['order_count']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-2xl font-bold text-green-600">₱<?php echo number_format($claim['commission_amount'], 2); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-600">
                                        <?php echo htmlspecialchars($claim['approved_by_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-600">
                                        <?php echo date('M j, Y g:i A', strtotime($claim['approved_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="showReleaseModal(<?php echo $claim['id']; ?>, '<?php echo htmlspecialchars($claim['sales_name']); ?>', <?php echo $claim['commission_amount']; ?>, <?php echo $claim['order_count']; ?>)" 
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                            <i class="fas fa-paper-plane mr-1"></i>Release Payment
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-12 text-center">
                    <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-700">No Approved Claims</h3>
                    <p class="text-gray-500 mt-2">No commission payments pending release</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Released History -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gray-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-history mr-2"></i>
                    Released Payments History
                </h2>
            </div>
            
            <?php if ($released_result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Sales Person</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Code</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Orders</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">Amount</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Released By</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($release = $released_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($release['sales_name']); ?></td>
                                    <td class="px-6 py-4 font-mono text-purple-700"><?php echo htmlspecialchars($release['referral_code']); ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-semibold">
                                            <?php echo number_format($release['order_count']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-green-600">₱<?php echo number_format($release['commission_amount'], 2); ?></td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-600"><?php echo htmlspecialchars($release['released_by_name']); ?></td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-600"><?php echo date('M j, Y', strtotime($release['released_at'])); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($release['notes'] ?: '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-12 text-center">
                    <i class="fas fa-history text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500">No payment history yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Release Modal -->
    <div id="releaseModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Release Commission Payment</h3>
            <div class="bg-blue-50 rounded-lg p-4 mb-4">
                <div class="text-sm text-gray-600 mb-1">Releasing to:</div>
                <div class="font-bold text-gray-900" id="modal_sales_name"></div>
                <div class="text-2xl font-bold text-green-600 mt-2" id="modal_amount"></div>
                <div class="text-sm text-gray-600 mt-1">
                    For <span id="modal_order_count" class="font-bold"></span> orders
                </div>
            </div>
            <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-3 mb-4">
                <p class="text-xs text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Note:</strong> Releasing this payment will permanently mark these orders as claimed. They cannot be claimed again.
                </p>
            </div>
            <form method="POST">
                <input type="hidden" name="claim_id" id="release_claim_id">
                <textarea name="release_notes" rows="3" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-4" 
                    placeholder="Payment notes (optional): e.g., Check #12345, Bank transfer ref..."></textarea>
                <div class="flex gap-3">
                    <button type="button" onclick="hideReleaseModal()" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" name="release_payment" 
                        class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-check mr-1"></i>Confirm Release
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showReleaseModal(claimId, salesName, amount, orderCount) {
            document.getElementById('release_claim_id').value = claimId;
            document.getElementById('modal_sales_name').textContent = salesName;
            document.getElementById('modal_amount').textContent = '₱' + amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('modal_order_count').textContent = orderCount;
            document.getElementById('releaseModal').classList.remove('hidden');
        }
        function hideReleaseModal() {
            document.getElementById('releaseModal').classList.add('hidden');
        }
    </script>
</body>
</html>
<?php 
$approved_stmt->close();
$released_stmt->close();
?>