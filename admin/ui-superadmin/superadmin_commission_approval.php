<?php
// superadmin_commission_approval.php
session_name("nobleadmin");
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$_SESSION['last_activity'] = time();
$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];

$message = "";
$error = "";

// Handle approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_claim'])) {
    $claim_id = intval($_POST['claim_id']);
    
    $approve_stmt = $conn->prepare("
        UPDATE commission_claims 
        SET status = 'approved', approved_by = ?, approved_at = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    $approve_stmt->bind_param("ii", $user_id, $claim_id);
    
    if ($approve_stmt->execute() && $approve_stmt->affected_rows > 0) {
        $_SESSION['success_message'] = "Commission claim approved successfully!";
    } else {
        $error = "Failed to approve claim.";
    }
    $approve_stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_claim'])) {
    $claim_id = intval($_POST['claim_id']);
    $reason = $_POST['rejection_reason'] ?? 'No reason provided';
    
    $reject_stmt = $conn->prepare("
        UPDATE commission_claims 
        SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? 
        WHERE id = ? AND status = 'pending'
    ");
    $reject_stmt->bind_param("isi", $user_id, $reason, $claim_id);
    
    if ($reject_stmt->execute()) {
        $_SESSION['success_message'] = "Commission claim rejected.";
    } else {
        $error = "Failed to reject claim.";
    }
    $reject_stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get success message
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Fetch all pending claims with order breakdown
$claims_stmt = $conn->prepare("
    SELECT 
        cc.id,
        cc.sales_user_id,
        cc.referral_code,
        cc.commission_amount,
        cc.claim_date,
        cc.status,
        cc.order_ids,
        cc.order_count,
        na.fullname as sales_name,
        na.email as sales_email
    FROM commission_claims cc
    INNER JOIN nobleaccount na ON cc.sales_user_id = na.id
    WHERE cc.status = 'pending'
    ORDER BY cc.claim_date ASC
");
$claims_stmt->execute();
$claims_result = $claims_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Approval - Superadmin</title>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                Commission Claims Approval
            </h1>
            <p class="text-gray-600 mt-2">Review and approve sales commission claims</p>
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

        <?php if ($claims_result->num_rows > 0): ?>
            <?php while ($claim = $claims_result->fetch_assoc()): ?>
                <?php
                // Get order details for this claim
                $order_ids = $claim['order_ids'];
                $order_details_stmt = $conn->prepare("
                    SELECT 
                        id,
                        reference_no,
                        customer_name,
                        email,
                        created_at,
                        subtotal,
                        sales_commission_rate,
                        sales_commission_amount,
                        payment_status
                    FROM orders 
                    WHERE FIND_IN_SET(id, ?)
                    ORDER BY created_at DESC
                ");
                $order_details_stmt->bind_param("s", $order_ids);
                $order_details_stmt->execute();
                $order_details_result = $order_details_stmt->get_result();
                ?>
                
                <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
                    <!-- Claim Header -->
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($claim['sales_name']); ?></h2>
                                <p class="text-purple-100 text-sm"><?php echo htmlspecialchars($claim['sales_email']); ?></p>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold text-white">₱<?php echo number_format($claim['commission_amount'], 2); ?></div>
                                <div class="text-purple-100 text-sm">Total Commission</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Claim Info -->
                    <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Referral Code:</span>
                                <span class="font-mono font-bold text-purple-700 ml-2"><?php echo htmlspecialchars($claim['referral_code']); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Orders:</span>
                                <span class="font-bold text-gray-900 ml-2"><?php echo number_format($claim['order_count']); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Claim Date:</span>
                                <span class="font-medium text-gray-900 ml-2"><?php echo date('M j, Y g:i A', strtotime($claim['claim_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Breakdown Table -->
                    <div class="p-6">
                        <h3 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-receipt mr-2 text-blue-600"></i>
                            Order Breakdown (Proof of Commission)
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100 border-b-2 border-gray-300">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Order #</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Customer</th>
                                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Date</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Order Value</th>
                                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Rate</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Commission</th>
                                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php 
                                    $total_verify = 0;
                                    while ($order = $order_details_result->fetch_assoc()): 
                                        $total_verify += $order['sales_commission_amount'];
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <span class="font-mono text-purple-700 font-semibold">
                                                    <?php echo htmlspecialchars($order['reference_no']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div>
                                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center text-xs text-gray-600">
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td class="px-4 py-3 text-right font-semibold text-blue-600">
                                                ₱<?php echo number_format($order['subtotal'], 2); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="bg-orange-100 text-orange-700 px-2 py-1 rounded text-xs font-bold">
                                                    <?php echo number_format($order['sales_commission_rate'], 1); ?>%
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right font-bold text-green-700">
                                                ₱<?php echo number_format($order['sales_commission_amount'], 2); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php
                                                $status_colors = [
                                                    'verified' => 'bg-green-100 text-green-700',
                                                    'paid' => 'bg-blue-100 text-blue-700',
                                                    'completed' => 'bg-purple-100 text-purple-700'
                                                ];
                                                $status_class = $status_colors[$order['payment_status']] ?? 'bg-gray-100 text-gray-700';
                                                ?>
                                                <span class="px-2 py-1 <?php echo $status_class; ?> rounded-full text-xs font-semibold">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-right font-bold text-gray-900">Total Commission:</td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="text-2xl font-bold text-green-600">₱<?php echo number_format($total_verify, 2); ?></span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                        <div class="flex gap-3 justify-end">
                            <button onclick="showRejectModal(<?php echo $claim['id']; ?>)" 
                                class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg font-medium">
                                <i class="fas fa-times mr-2"></i>Reject Claim
                            </button>
                            <form method="POST" class="inline">
                                <input type="hidden" name="claim_id" value="<?php echo $claim['id']; ?>">
                                <button type="submit" name="approve_claim" 
                                    class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium">
                                    <i class="fas fa-check mr-2"></i>Approve Claim
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <?php $order_details_stmt->close(); ?>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-xl font-bold text-gray-700">No Pending Claims</h3>
                <p class="text-gray-500 mt-2">All commission claims have been processed</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Reject Commission Claim</h3>
            <form method="POST">
                <input type="hidden" name="claim_id" id="reject_claim_id">
                <textarea name="rejection_reason" rows="4" required 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-4" 
                    placeholder="Enter reason for rejection..."></textarea>
                <div class="flex gap-3">
                    <button type="button" onclick="hideRejectModal()" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" name="reject_claim" 
                        class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showRejectModal(claimId) {
            document.getElementById('reject_claim_id').value = claimId;
            document.getElementById('rejectModal').classList.remove('hidden');
        }
        function hideRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }
    </script>
</body>
</html>
<?php $claims_stmt->close(); ?>