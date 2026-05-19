<?php
// accountant_commission_release.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['accountant', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$_SESSION['last_activity'] = time();
$user_id  = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];

$message = "";
$error   = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_payment'])) {
    $claim_id = intval($_POST['claim_id']);
    $notes    = $_POST['release_notes'] ?? '';

    $conn->begin_transaction();
    try {
        $get_orders_stmt = $conn->prepare("SELECT order_ids FROM commission_claims WHERE id = ?");
        $get_orders_stmt->bind_param("i", $claim_id);
        $get_orders_stmt->execute();
        $get_orders_stmt->bind_result($order_ids);
        $get_orders_stmt->fetch();
        $get_orders_stmt->close();

        if ($order_ids) {
            $order_ids_array  = explode(',', $order_ids);
            $placeholders     = implode(',', array_fill(0, count($order_ids_array), '?'));
            $mark_claimed_stmt = $conn->prepare("UPDATE orders SET commission_claimed = 1 WHERE id IN ($placeholders)");
            $types = str_repeat('i', count($order_ids_array));
            $mark_claimed_stmt->bind_param($types, ...$order_ids_array);
            $mark_claimed_stmt->execute();
            $mark_claimed_stmt->close();
        }

        $release_stmt = $conn->prepare("UPDATE commission_claims SET status = 'released', released_by = ?, released_at = NOW(), notes = ? WHERE id = ? AND status = 'approved'");
        $release_stmt->bind_param("isi", $user_id, $notes, $claim_id);
        $release_stmt->execute();
        $release_stmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Commission payment released successfully! Orders marked as claimed.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to release payment: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$approved_stmt = $conn->prepare("
    SELECT cc.id, cc.sales_user_id, cc.referral_code, cc.commission_amount,
           cc.claim_date, cc.approved_at, cc.status, cc.order_count,
           na.fullname as sales_name, na.email as sales_email,
           approver.fullname as approved_by_name
    FROM commission_claims cc
    INNER JOIN nobleaccount na ON cc.sales_user_id = na.id
    LEFT JOIN nobleaccount approver ON cc.approved_by = approver.id
    WHERE cc.status = 'approved'
    ORDER BY cc.approved_at ASC
");
$approved_stmt->execute();
$approved_result = $approved_stmt->get_result();

$released_stmt = $conn->prepare("
    SELECT cc.id, cc.sales_user_id, cc.referral_code, cc.commission_amount,
           cc.released_at, cc.notes, cc.order_count,
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
    <title>Commission Release — Accountant</title>
    <style>
        tbody tr { transition: background 0.1s; }
        tbody tr:hover { background: #f9fafb; }
        .modal-backdrop { position: fixed; inset: 0; z-index: 50; background: rgba(0,0,0,0.45); overflow: auto; }
        .modal-backdrop.hidden { display: none; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

<div class="max-w-screen-xl mx-auto px-4 py-8 space-y-6">

    <!-- Page Header -->
    <div>
        <h1 class="text-xl font-bold text-gray-900">Commission Payment Release</h1>
        <p class="text-sm text-gray-500 mt-0.5">Release approved commission payments to sales staff</p>
    </div>

    <!-- Flash Messages -->
    <?php if ($message): ?>
        <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
            <i class="fas fa-check-circle text-green-500"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
            <i class="fas fa-exclamation-circle text-red-500"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- ── Ready for Release ── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                <h2 class="text-sm font-semibold text-gray-800">Ready for Release</h2>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="background:#dbeafe;color:#1d4ed8;">
                <?php echo $approved_result->num_rows; ?> pending
            </span>
        </div>

        <?php if ($approved_result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-5 py-3 text-left">Sales Person</th>
                        <th class="px-5 py-3 text-left">Code</th>
                        <th class="px-5 py-3 text-center">Orders</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3 text-center">Approved By</th>
                        <th class="px-5 py-3 text-center">Approved Date</th>
                        <th class="px-5 py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while ($claim = $approved_result->fetch_assoc()): ?>
                    <tr>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-xs font-bold text-blue-600" style="background:#dbeafe;">
                                    <?php echo strtoupper(substr($claim['sales_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 leading-snug"><?php echo htmlspecialchars($claim['sales_name']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($claim['sales_email']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <span class="font-mono text-xs font-bold px-2 py-1 rounded" style="background:#f3e8ff;color:#7e22ce;">
                                <?php echo htmlspecialchars($claim['referral_code']); ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="background:#dbeafe;color:#1d4ed8;">
                                <?php echo number_format($claim['order_count']); ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-base font-bold" style="color:#15803d;">₱<?php echo number_format($claim['commission_amount'], 2); ?></span>
                        </td>
                        <td class="px-5 py-3 text-center text-xs text-gray-600">
                            <?php echo htmlspecialchars($claim['approved_by_name']); ?>
                        </td>
                        <td class="px-5 py-3 text-center text-xs text-gray-600">
                            <?php echo date('M j, Y g:i A', strtotime($claim['approved_at'])); ?>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <button onclick="showReleaseModal(<?php echo $claim['id']; ?>, '<?php echo htmlspecialchars($claim['sales_name'], ENT_QUOTES); ?>', <?php echo $claim['commission_amount']; ?>, <?php echo $claim['order_count']; ?>)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-white transition"
                                style="background:#2563eb;">
                                <i class="fas fa-paper-plane"></i>Release
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <div class="py-14 text-center">
            <i class="fas fa-inbox text-5xl text-gray-200 mb-3"></i>
            <p class="text-sm font-semibold text-gray-600">No Approved Claims</p>
            <p class="text-xs text-gray-400 mt-1">No commission payments pending release</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Released History ── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-gray-400"></div>
            <h2 class="text-sm font-semibold text-gray-800">Released Payments History</h2>
        </div>

        <?php if ($released_result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-5 py-3 text-left">Sales Person</th>
                        <th class="px-5 py-3 text-left">Code</th>
                        <th class="px-5 py-3 text-center">Orders</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3 text-center">Released By</th>
                        <th class="px-5 py-3 text-center">Date</th>
                        <th class="px-5 py-3 text-left">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while ($release = $released_result->fetch_assoc()): ?>
                    <tr>
                        <td class="px-5 py-3 font-medium text-gray-900"><?php echo htmlspecialchars($release['sales_name']); ?></td>
                        <td class="px-5 py-3">
                            <span class="font-mono text-xs font-bold px-2 py-1 rounded" style="background:#f3e8ff;color:#7e22ce;">
                                <?php echo htmlspecialchars($release['referral_code']); ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-full" style="background:#f3f4f6;color:#374151;">
                                <?php echo number_format($release['order_count']); ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right font-bold" style="color:#15803d;">₱<?php echo number_format($release['commission_amount'], 2); ?></td>
                        <td class="px-5 py-3 text-center text-xs text-gray-600"><?php echo htmlspecialchars($release['released_by_name']); ?></td>
                        <td class="px-5 py-3 text-center text-xs text-gray-600"><?php echo date('M j, Y', strtotime($release['released_at'])); ?></td>
                        <td class="px-5 py-3 text-xs text-gray-500"><?php echo htmlspecialchars($release['notes'] ?: '—'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <div class="py-14 text-center">
            <i class="fas fa-history text-5xl text-gray-200 mb-3"></i>
            <p class="text-xs text-gray-400">No payment history yet</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- ── Release Modal ── -->
<div id="releaseModal" class="modal-backdrop hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="p-6">

                <!-- Header -->
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#dbeafe;">
                        <i class="fas fa-paper-plane text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Release Commission Payment</h3>
                        <p class="text-xs text-gray-400">This action cannot be undone</p>
                    </div>
                </div>

                <!-- Summary Card -->
                <div class="rounded-xl p-4 mb-4 border border-gray-100" style="background:#f8fafc;">
                    <p class="text-xs text-gray-400 mb-1">Releasing to</p>
                    <p class="font-bold text-gray-900 text-sm" id="modal_sales_name"></p>
                    <p class="text-2xl font-bold mt-1" id="modal_amount" style="color:#15803d;"></p>
                    <p class="text-xs text-gray-500 mt-1">For <span id="modal_order_count" class="font-semibold text-gray-700"></span> order(s)</p>
                </div>

                <!-- Warning -->
                <div class="flex items-start gap-2 rounded-lg p-3 mb-5 border" style="background:#fefce8;border-color:#fde047;">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-xs mt-0.5 shrink-0"></i>
                    <p class="text-xs" style="color:#a16207;">
                        Releasing this payment will permanently mark these orders as claimed. They cannot be claimed again.
                    </p>
                </div>

                <form method="POST">
                    <input type="hidden" name="claim_id" id="release_claim_id">
                    <textarea name="release_notes" rows="3"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none mb-4"
                        placeholder="Payment notes (optional): e.g. Check #12345, Bank transfer ref..."></textarea>
                    <div class="flex gap-2">
                        <button type="button" onclick="hideReleaseModal()"
                            class="flex-1 px-4 py-2 text-sm rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                            Cancel
                        </button>
                        <button type="submit" name="release_payment"
                            class="flex-1 px-4 py-2 text-sm rounded-lg text-white font-medium transition"
                            style="background:#2563eb;">
                            <i class="fas fa-check mr-1.5"></i>Confirm Release
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
    function showReleaseModal(claimId, salesName, amount, orderCount) {
        document.getElementById('release_claim_id').value = claimId;
        document.getElementById('modal_sales_name').textContent = salesName;
        document.getElementById('modal_amount').textContent = '₱' + Number(amount).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('modal_order_count').textContent = orderCount;
        document.getElementById('releaseModal').classList.remove('hidden');
    }
    function hideReleaseModal() {
        document.getElementById('releaseModal').classList.add('hidden');
    }
    document.getElementById('releaseModal').addEventListener('click', function(e) {
        if (e.target === this) hideReleaseModal();
    });
</script>

</body>
</html>
<?php
$approved_stmt->close();
$released_stmt->close();
?>