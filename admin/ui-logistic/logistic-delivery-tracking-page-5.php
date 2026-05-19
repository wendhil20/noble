<?php
// delivery_tracking.php - REDESIGNED
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);
require_once ROOT_PATH . '/admin/ui-warehouse/audit_trail_helper.php';

if (isset($_SESSION['noble_subrole']) && $_SESSION['noble_subrole'] === 'dispatcher') {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

$sql = "SELECT 
    db.*,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.delivery_type,
    ds.item_type,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.final_total,
    o.status as order_status,
    tv.vehicle_type,
    COALESCE(db.courier_name, tv.courier_name) as courier_name,
    dispatcher.fullname as dispatcher_name,
    dispatcher.email as dispatcher_email,
    CASE WHEN ds.item_type = 'replacement' THEN 
        (SELECT COUNT(*) FROM replacement_requests WHERE delivery_schedule_id = ds.id)
    ELSE (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id)
    END as total_items,
    CASE WHEN ds.item_type = 'replacement' THEN 
        (SELECT COUNT(*) FROM replacement_requests WHERE delivery_schedule_id = ds.id AND status = 'ready_for_pickup')
    ELSE (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id AND tracking_status = 'ready_for_pickup')
    END as ready_items,
    CASE WHEN ds.item_type = 'replacement' THEN 
        (SELECT COUNT(*) FROM replacement_requests WHERE delivery_schedule_id = ds.id AND status IN ('item_is_loaded', 'out_for_delivery'))
    ELSE (SELECT COUNT(*) FROM order_items WHERE order_id = db.order_id AND tracking_status IN ('item_is_loaded', 'delivered'))
    END as loaded_items
FROM delivery_bookings db
INNER JOIN delivery_schedules ds ON db.delivery_schedule_id = ds.id
INNER JOIN orders o ON db.order_id = o.id
LEFT JOIN transportify_vehicle_list tv ON db.vehicle_id = tv.id
LEFT JOIN nobleaccount dispatcher ON db.dispatcher_id = dispatcher.id
WHERE db.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header("Location: " . BASE_URL . "/logistic");
    exit();
}

$isReplacement = ($booking['item_type'] === 'replacement');

$replacementDetails = [];
$totalReplacementQty = 0;
if ($isReplacement) {
    $replSql = "SELECT 
        rr.*,
        oi.product_name,
        oi.variant_color,
        oi.size,
        oi.price
    FROM replacement_requests rr
    INNER JOIN order_items oi ON rr.order_item_id = oi.id
    WHERE rr.delivery_schedule_id = (SELECT delivery_schedule_id FROM delivery_bookings WHERE id = ?)
    ORDER BY oi.product_name";

    $replStmt = $conn->prepare($replSql);
    $replStmt->bind_param("i", $booking_id);
    $replStmt->execute();
    $replacementDetails = $replStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $replStmt->close();

    foreach ($replacementDetails as $repl) {
        $totalReplacementQty += $repl['replacement_quantity'];
    }
}

if ($isReplacement) {
    $itemsSql = "SELECT 
        oi.id,
        oi.product_name,
        oi.variant_color,
        oi.size,
        oi.price,
        oi.warehouse_location,
        rr.id as replacement_id,
        rr.replacement_quantity as quantity,
        rr.reason as replacement_reason,
        rr.details as replacement_details,
        rr.status as replacement_status
    FROM order_items oi
    INNER JOIN replacement_requests rr ON oi.id = rr.order_item_id
    WHERE rr.delivery_schedule_id = (SELECT delivery_schedule_id FROM delivery_bookings WHERE id = ?)
    ORDER BY oi.product_name";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $booking_id);
} else {
    $itemsSql = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $booking['order_id']);
}
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

$dispatchersSql = "SELECT 
    id, 
    fullname, 
    email,
    (SELECT COUNT(*) FROM delivery_bookings WHERE dispatcher_id = nobleaccount.id AND booking_status NOT IN ('delivered', 'picked_up', 'cancelled')) as active_bookings
FROM nobleaccount 
WHERE lvl = 'logistic' 
AND subrole = 'dispatcher' 
AND status = 'active'
ORDER BY active_bookings ASC, fullname ASC";
$dispatchersResult = $conn->query($dispatchersSql);
$dispatchers = $dispatchersResult->fetch_all(MYSQLI_ASSOC);


function createDeliveryNotification($conn, $booking_id, $order_id, $booking_type)
{
    try {
        $stmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
        if (!$stmt)
            return false;
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result || !$result['user_id'])
            return false;

        $customer_id = (int) $result['user_id'];

        if ($booking_type === 'pickup') {
            $message = "Your order #$order_id has been picked up!";
            $type = "PICKUP_COMPLETED";
        } else {
            $message = "Your order #$order_id has been delivered! Please review the products";
            $type = "DELIVERY_COMPLETED";
        }

        $sql = "INSERT INTO notifications (user_id, actor_id, type, message, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            return false;

        $actor_id = null;
        $stmt->bind_param("iiss", $customer_id, $actor_id, $type, $message);
        $success = $stmt->execute();
        $stmt->close();
        return $success ? true : false;

    } catch (Exception $e) {
        error_log("Exception in createDeliveryNotification: " . $e->getMessage());
        return false;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'assign_dispatcher') {
        $dispatcher_id = !empty($_POST['dispatcher_id']) ? intval($_POST['dispatcher_id']) : null;

        $oldDispatcherStmt = $conn->prepare("SELECT dispatcher_id FROM delivery_bookings WHERE id = ?");
        $oldDispatcherStmt->bind_param("i", $booking_id);
        $oldDispatcherStmt->execute();
        $oldDispatcherResult = $oldDispatcherStmt->get_result()->fetch_assoc();
        $old_dispatcher_id = $oldDispatcherResult['dispatcher_id'];
        $oldDispatcherStmt->close();

        $old_dispatcher_name = 'Unassigned';
        $new_dispatcher_name = 'Unassigned';

        if ($old_dispatcher_id) {
            $oldNameStmt = $conn->prepare("SELECT fullname FROM nobleaccount WHERE id = ?");
            $oldNameStmt->bind_param("i", $old_dispatcher_id);
            $oldNameStmt->execute();
            $oldNameResult = $oldNameStmt->get_result()->fetch_assoc();
            $old_dispatcher_name = $oldNameResult['fullname'] ?? 'Unknown';
            $oldNameStmt->close();
        }

        if ($dispatcher_id) {
            $newNameStmt = $conn->prepare("SELECT fullname FROM nobleaccount WHERE id = ?");
            $newNameStmt->bind_param("i", $dispatcher_id);
            $newNameStmt->execute();
            $newNameResult = $newNameStmt->get_result()->fetch_assoc();
            $new_dispatcher_name = $newNameResult['fullname'] ?? 'Unknown';
            $newNameStmt->close();
        }

        $updateDispatcher = $conn->prepare("UPDATE delivery_bookings SET dispatcher_id = ? WHERE id = ?");
        $updateDispatcher->bind_param("ii", $dispatcher_id, $booking_id);

        if ($updateDispatcher->execute()) {
            logAuditTrail($conn, 'ASSIGN_DISPATCHER', 'delivery_bookings', $booking_id, $booking['order_id'], null, $old_dispatcher_name, $new_dispatcher_name, $dispatcher_id ? "Assigned dispatcher: $new_dispatcher_name (was: $old_dispatcher_name)" : "Unassigned dispatcher (was: $old_dispatcher_name)");
            $_SESSION['success_message'] = $dispatcher_id ? "Dispatcher assigned successfully!" : "Dispatcher unassigned successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to assign dispatcher";
        }
        $updateDispatcher->close();

        header("Location: " . BASE_URL . "/logisticdeliverytrack?booking_id=" . $booking_id);
        exit();
    }

    if ($_POST['action'] === 'upload_proof') {
        try {
            if (!isset($_FILES['delivery_proof']) || $_FILES['delivery_proof']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("No file uploaded or upload error occurred");
            }

            $upload_dir = ROOT_PATH . '/uploads/delivery_proofs/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true))
                    throw new Exception("Failed to create upload directory");
            }

            $file = $_FILES['delivery_proof'];
            $image_info = @getimagesize($file['tmp_name']);
            if ($image_info === false)
                throw new Exception("Invalid image file");

            $mime_type = $image_info['mime'];
            $image = null;
            switch ($mime_type) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($file['tmp_name']);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($file['tmp_name']);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($file['tmp_name']);
                    break;
                default:
                    throw new Exception("Unsupported image format: $mime_type");
            }

            if ($image === false)
                throw new Exception("Failed to create image resource");

            $webp_filename = 'proof_' . $booking_id . '_' . time() . '.webp';
            $webp_path = $upload_dir . $webp_filename;

            if (!imagewebp($image, $webp_path, 85)) {
                imagedestroy($image);
                throw new Exception("Failed to convert image to WebP format");
            }
            imagedestroy($image);

            $oldStatusStmt = $conn->prepare("SELECT booking_status FROM delivery_bookings WHERE id = ?");
            $oldStatusStmt->bind_param("i", $booking_id);
            $oldStatusStmt->execute();
            $old_status = $oldStatusStmt->get_result()->fetch_assoc()['booking_status'];
            $oldStatusStmt->close();

            $new_status = ($booking['booking_type'] === 'pickup') ? 'picked_up' : 'delivered';

            $updateProof = $conn->prepare("UPDATE delivery_bookings SET delivery_proof_image = ?, booking_status = ?, actual_delivery_time = CURRENT_TIMESTAMP WHERE id = ?");
            if (!$updateProof)
                throw new Exception("Prepare error: " . $conn->error);
            $updateProof->bind_param("ssi", $webp_filename, $new_status, $booking_id);
            if (!$updateProof->execute())
                throw new Exception("Failed to update delivery booking: " . $updateProof->error);
            $updateProof->close();

            logAuditTrail($conn, 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', $booking_id, $booking['order_id'], null, json_encode(['old_status' => $old_status, 'proof_image' => null]), json_encode(['new_status' => $new_status, 'proof_image' => $webp_filename]), ($booking['booking_type'] === 'pickup' ? 'Pickup' : 'Delivery') . " proof uploaded and status updated from '$old_status' to '$new_status'" . ($isReplacement ? ' (Replacement)' : ''));

            createDeliveryNotification($conn, $booking_id, $booking['order_id'], $booking['booking_type']);

            if ($isReplacement) {
                $replacement_final_status = ($booking['booking_type'] === 'pickup') ? 'picked_up' : 'delivered';
                $updateReplacement = $conn->prepare("UPDATE replacement_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE delivery_schedule_id = (SELECT delivery_schedule_id FROM delivery_bookings WHERE id = ?)");
                $updateReplacement->bind_param("si", $replacement_final_status, $booking_id);
                $updateReplacement->execute();
                $updateReplacement->close();

                $order_final_status = ($booking['booking_type'] === 'pickup') ? 'Picked Up' : 'Delivered';
                $updateOrder = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $updateOrder->bind_param("si", $order_final_status, $booking['order_id']);
                $updateOrder->execute();
                $updateOrder->close();

                $item_status = ($booking['booking_type'] === 'pickup') ? 'picked_up' : 'delivered';
                $updateReplacementItem = $conn->prepare("UPDATE order_items SET tracking_status = ? WHERE id IN (SELECT order_item_id FROM replacement_requests WHERE delivery_schedule_id = (SELECT delivery_schedule_id FROM delivery_bookings WHERE id = ?))");
                $updateReplacementItem->bind_param("si", $item_status, $booking_id);
                $updateReplacementItem->execute();
                $updateReplacementItem->close();

                $_SESSION['success_message'] = "Replacement " . ($booking['booking_type'] === 'pickup' ? 'pickup' : 'delivery') . " completed successfully!";
            } else {
                $final_status = ($booking['booking_type'] === 'pickup') ? 'Picked Up' : 'Delivered';
                $updateOrder = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $updateOrder->bind_param("si", $final_status, $booking['order_id']);
                $updateOrder->execute();
                $updateOrder->close();

                $item_status = ($booking['booking_type'] === 'pickup') ? 'picked_up' : 'delivered';
                $updateItems = $conn->prepare("UPDATE order_items SET tracking_status = ? WHERE order_id = ?");
                $updateItems->bind_param("si", $item_status, $booking['order_id']);
                $updateItems->execute();
                $updateItems->close();

                $_SESSION['success_message'] = "Delivery proof uploaded successfully!";
            }

        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }

        header("Location: " . BASE_URL . "/logisticdeliverytrack?booking_id=" . $booking_id);
        exit();
    }
}

$isCompleted = in_array($booking['booking_status'], ['delivered', 'picked_up']);
$allLoaded = $booking['loaded_items'] === $booking['total_items'] && $booking['total_items'] > 0;
$loadingPercent = $booking['total_items'] > 0 ? round(($booking['loaded_items'] / $booking['total_items']) * 100) : 0;

// Status badge helper
function statusBadge($status)
{
    $map = [
        'delivered' => ['bg-green-100 text-green-800', 'fa-check-circle'],
        'picked_up' => ['bg-green-100 text-green-800', 'fa-check-circle'],
        'out_for_delivery' => ['bg-blue-100 text-blue-800', 'fa-truck'],
        'item_is_loaded' => ['bg-indigo-100 text-indigo-800', 'fa-box'],
        'ready_for_pickup' => ['bg-yellow-100 text-yellow-800', 'fa-clock'],
        'pending' => ['bg-gray-100 text-gray-700', 'fa-hourglass-half'],
        'cancelled' => ['bg-red-100 text-red-800', 'fa-times-circle'],
    ];
    $key = strtolower($status);
    [$cls, $icon] = $map[$key] ?? ['bg-gray-100 text-gray-700', 'fa-circle'];
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold $cls\"><i class=\"fas $icon\"></i>$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?php echo ucfirst($booking['booking_type']); ?> — Noble Home</title>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . "/admin/navbar/top.php"; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- ── Page Header ── -->
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <a href="<?= BASE_URL ?>/logisticdeliverydateorders?date=<?= $booking['delivery_date'] ?>"
                    class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 mb-3 transition-colors">
                    <i class="fas fa-arrow-left text-xs"></i> Back to Orders
                </a>

                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2 flex-wrap">
                    <?php if ($isReplacement): ?>
                        <span class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                            <i class="fas fa-sync-alt text-orange-600 text-sm"></i>
                        </span>
                        Replacement <?= ucfirst($booking['booking_type']) ?>
                    <?php else: ?>
                        <span class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-truck text-blue-600 text-sm"></i>
                        </span>
                        Manage <?= ucfirst($booking['booking_type']) ?>
                    <?php endif; ?>
                </h1>

                <p class="text-sm text-gray-500 mt-1">
                    Order <span class="font-semibold text-gray-700">#<?= $booking['order_id'] ?></span>
                    &nbsp;·&nbsp;
                    Booking <span class="font-semibold text-gray-700">#<?= $booking_id ?></span>
                    <?php if ($isReplacement): ?>
                        &nbsp;<span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 text-xs font-semibold">
                            <i class="fas fa-sync-alt"></i> REPLACEMENT
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Overall status pill -->
            <div class="flex items-center gap-2 self-start mt-1">
                <?= statusBadge($booking['booking_status']) ?>
            </div>
        </div>

        <!-- ── Flash Messages ── -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div
                class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                <i class="fas fa-check-circle text-green-500"></i>
                <?= $_SESSION['success_message'];
                unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <?= $_SESSION['error_message'];
                unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- ── Main Grid ── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ══ LEFT COLUMN ══ -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Booking Info -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-info-circle text-gray-400"></i>
                        <h2 class="font-semibold text-gray-800">
                            <?= $isReplacement ? 'Replacement' : 'Booking' ?> Information
                        </h2>
                    </div>

                    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Tracking # -->
                        <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                            <p class="text-xs text-gray-500 mb-1">Tracking Number</p>
                            <p class="font-mono font-bold text-gray-900 break-all">
                                <?= htmlspecialchars($booking['tracking_number']) ?></p>
                        </div>

                        <!-- Courier -->
                        <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                            <p class="text-xs text-gray-500 mb-1">Courier</p>
                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($booking['courier_name']) ?></p>
                        </div>

                        <?php if ($booking['booking_reference']): ?>
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                <p class="text-xs text-gray-500 mb-1">Booking Reference</p>
                                <p class="font-semibold text-gray-900">
                                    <?= htmlspecialchars($booking['booking_reference']) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($booking['booking_type'] === 'pickup'): ?>
                            <?php if ($booking['pickup_person_name']): ?>
                                <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                    <p class="text-xs text-gray-500 mb-1">Pickup Person</p>
                                    <p class="font-semibold text-gray-900">
                                        <?= htmlspecialchars($booking['pickup_person_name']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($booking['pickup_person_contact']): ?>
                                <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                    <p class="text-xs text-gray-500 mb-1">Contact Number</p>
                                    <p class="font-semibold text-gray-900">
                                        <?= htmlspecialchars($booking['pickup_person_contact']) ?></p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($booking['booking_type'] === 'delivery' && $booking['driver_name']): ?>
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                <p class="text-xs text-gray-500 mb-1">Driver</p>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($booking['driver_name']) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($booking['vehicle_plate_number']): ?>
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                <p class="text-xs text-gray-500 mb-1">Plate Number</p>
                                <p class="font-mono font-bold text-gray-900">
                                    <?= htmlspecialchars($booking['vehicle_plate_number']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Replacement Details Block -->
                    <?php if ($isReplacement && !empty($replacementDetails)): ?>
                        <div class="border-t border-gray-100 px-6 py-5">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fas fa-sync-alt text-orange-500 text-sm"></i>
                                <h3 class="font-semibold text-gray-800">Replacement Details</h3>
                                <span
                                    class="ml-auto text-xs font-semibold bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">
                                    <?= count($replacementDetails) ?> item(s)
                                </span>
                            </div>

                            <!-- Stats row -->
                            <div class="grid grid-cols-3 gap-3 mb-4">
                                <div class="text-center bg-orange-50 rounded-lg py-3">
                                    <p class="text-xs text-orange-600 mb-1">Items</p>
                                    <p class="text-2xl font-bold text-orange-900"><?= count($replacementDetails) ?></p>
                                </div>
                                <div class="text-center bg-orange-50 rounded-lg py-3">
                                    <p class="text-xs text-orange-600 mb-1">Total Qty</p>
                                    <p class="text-2xl font-bold text-orange-900"><?= $totalReplacementQty ?></p>
                                </div>
                                <div class="text-center bg-orange-50 rounded-lg py-3">
                                    <p class="text-xs text-orange-600 mb-1">Total Value</p>
                                    <p class="text-xl font-bold text-orange-900">
                                        ₱<?php
                                        $tv = 0;
                                        foreach ($replacementDetails as $r)
                                            $tv += $r['price'] * $r['replacement_quantity'];
                                        echo number_format($tv, 2);
                                        ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Replacement item cards -->
                            <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                                <?php foreach ($replacementDetails as $i => $repl): ?>
                                    <div class="border border-orange-200 rounded-lg p-4 bg-orange-50">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                                    <span
                                                        class="text-xs font-bold text-orange-700 bg-orange-200 px-1.5 py-0.5 rounded">#<?= $i + 1 ?></span>
                                                    <span class="text-xs text-orange-600">Req #<?= $repl['id'] ?></span>
                                                </div>
                                                <p class="font-semibold text-gray-900 truncate">
                                                    <?= htmlspecialchars($repl['product_name']) ?></p>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    <?php if ($repl['variant_color'])
                                                        echo 'Color: ' . htmlspecialchars($repl['variant_color']); ?>
                                                    <?php if ($repl['size'])
                                                        echo ' · Size: ' . htmlspecialchars($repl['size']); ?>
                                                </p>
                                                <p class="text-xs text-orange-700 mt-1">
                                                    Reason: <span
                                                        class="font-medium capitalize"><?= str_replace('_', ' ', $repl['reason']) ?></span>
                                                </p>
                                                <?php if ($repl['details']): ?>
                                                    <p class="text-xs text-gray-500 mt-1 line-clamp-2">
                                                        <?= nl2br(htmlspecialchars($repl['details'])) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <p class="font-bold text-gray-900"><?= $repl['replacement_quantity'] ?> pcs</p>
                                                <p class="text-xs text-gray-500">
                                                    ₱<?= number_format($repl['price'] * $repl['replacement_quantity'], 2) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Dispatcher Assignment -->
                    <div class="border-t border-gray-100 px-6 py-5">
                        <div class="flex items-center gap-2 mb-4">
                            <i class="fas fa-user-check text-gray-400 text-sm"></i>
                            <h3 class="font-semibold text-gray-800">Dispatcher</h3>
                        </div>

                        <?php if (!$isCompleted): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_dispatcher">
                                <select name="dispatcher_id" onchange="this.form.submit()"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">— Unassigned —</option>
                                    <?php foreach ($dispatchers as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= $booking['dispatcher_id'] == $d['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['fullname']) ?> (<?= $d['active_bookings'] ?> active)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Auto-saves on selection · Shows active bookings per
                                    dispatcher</p>
                            </form>
                        <?php elseif ($booking['dispatcher_name']): ?>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <div
                                    class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm shrink-0">
                                    <?= strtoupper(substr($booking['dispatcher_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm">
                                        <?= htmlspecialchars($booking['dispatcher_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($booking['dispatcher_email']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-400 italic">No dispatcher assigned</p>
                        <?php endif; ?>
                    </div>

                    <!-- Delivery Proof Upload -->
                    <?php if ($allLoaded && !$booking['delivery_proof_image']): ?>
                        <div class="border-t border-gray-100 px-6 py-5">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fas fa-camera text-gray-400 text-sm"></i>
                                <h3 class="font-semibold text-gray-800">Upload
                                    <?= $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Delivery' ?> Proof</h3>
                            </div>
                            <form method="POST" enctype="multipart/form-data" class="space-y-3">
                                <input type="hidden" name="action" value="upload_proof">
                                <div class="border-2 border-dashed border-gray-200 rounded-lg p-4 text-center hover:border-blue-400 transition-colors cursor-pointer"
                                    onclick="document.getElementById('proofInput').click()">
                                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-300 mb-2 block"></i>
                                    <p class="text-sm text-gray-500">Click to select an image</p>
                                    <p class="text-xs text-gray-400 mt-1">JPEG, PNG, WebP, GIF · Converted to WebP</p>
                                    <input id="proofInput" type="file" name="delivery_proof" accept="image/*" required
                                        class="hidden"
                                        onchange="document.getElementById('fileLabel').textContent = this.files[0]?.name || 'No file chosen'">
                                </div>
                                <p id="fileLabel" class="text-xs text-center text-gray-400">No file chosen</p>
                                <button type="submit"
                                    class="w-full py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                                    <i class="fas fa-upload"></i>
                                    Upload & Complete <?= ucfirst($booking['booking_type']) ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Delivery Proof Image -->
                    <?php if ($booking['delivery_proof_image']): ?>
                        <div class="border-t border-gray-100 px-6 py-5">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fas fa-check-circle text-green-500 text-sm"></i>
                                <h3 class="font-semibold text-gray-800">
                                    <?= $booking['booking_type'] === 'pickup' ? 'Pickup' : 'Delivery' ?> Proof</h3>
                            </div>
                            <div class="flex justify-center">
                                <img src="<?= BASE_URL ?>/uploads/delivery_proofs/<?= htmlspecialchars($booking['delivery_proof_image']) ?>"
                                    alt="Delivery Proof"
                                    class="max-w-sm w-full rounded-lg border border-gray-200 shadow-sm cursor-pointer hover:opacity-90 transition-opacity"
                                    onclick="openModal(this.src)">
                            </div>
                            <p class="text-xs text-center text-gray-400 mt-2">Click to enlarge</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Items Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-boxes text-gray-400"></i>
                            <h2 class="font-semibold text-gray-800">
                                <?= $isReplacement ? 'Replacement Items' : 'Order Items' ?></h2>
                        </div>
                        <span class="text-xs text-gray-500"><?= count($items) ?>
                            item<?= count($items) !== 1 ? 's' : '' ?></span>
                    </div>

                    <div class="p-6">
                        <!-- Progress bar (regular orders only) -->
                        <?php if (!$isReplacement): ?>
                            <div class="mb-5">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-medium text-gray-600">Loading progress</span>
                                    <span
                                        class="text-xs font-semibold text-gray-800"><?= $booking['loaded_items'] ?>/<?= $booking['total_items'] ?>
                                        items</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all duration-500 <?= $loadingPercent >= 100 ? 'bg-green-500' : 'bg-blue-500' ?>"
                                        style="width: <?= $loadingPercent ?>%"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1"><?= $loadingPercent ?>% loaded</p>
                            </div>
                        <?php elseif (count($items) > 0): ?>
                            <div
                                class="mb-4 flex items-center justify-between py-2 px-3 bg-orange-50 border border-orange-200 rounded-lg">
                                <span class="text-sm font-medium text-orange-800">Replacement Status</span>
                                <?= statusBadge($items[0]['replacement_status']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Item list -->
                        <div class="space-y-2">
                            <?php if ($isReplacement): ?>
                                <?php foreach ($items as $item):
                                    $rs = strtolower($item['replacement_status']);
                                    $isFinal = in_array($rs, ['delivered', 'picked_up']);
                                    ?>
                                    <div
                                        class="flex items-start justify-between gap-3 p-4 rounded-lg border <?= $isFinal ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200' ?>">
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900 text-sm">
                                                <?= htmlspecialchars($item['product_name']) ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                <?php if ($item['variant_color'])
                                                    echo 'Color: ' . htmlspecialchars($item['variant_color']); ?>
                                                <?php if ($item['size'])
                                                    echo ' · Size: ' . htmlspecialchars($item['size']); ?>
                                                · <span class="font-medium">Qty: <?= $item['quantity'] ?></span>
                                            </p>
                                            <?php if (!empty($item['warehouse_location'])): ?>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <i class="fas fa-map-marker-alt text-red-400 mr-1"></i>
                                                    <?= htmlspecialchars($item['warehouse_location']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <span
                                                class="inline-flex items-center gap-1 mt-1.5 text-xs text-orange-700 bg-orange-100 px-2 py-0.5 rounded-full">
                                                <i class="fas fa-sync-alt text-[10px]"></i>
                                                <?= ucwords(str_replace('_', ' ', $item['replacement_reason'])) ?>
                                            </span>
                                        </div>
                                        <div class="shrink-0"><?= statusBadge($item['replacement_status']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <div class="flex items-start justify-between gap-3 p-4 rounded-lg border
                                <?php
                                $ts = $item['tracking_status'];
                                echo in_array($ts, ['delivered', 'picked_up']) ? 'bg-green-50 border-green-200' : ($ts === 'item_is_loaded' ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200');
                                ?>">
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900 text-sm">
                                                <?= htmlspecialchars($item['product_name']) ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                <?php if ($item['variant_color'])
                                                    echo 'Color: ' . htmlspecialchars($item['variant_color']); ?>
                                                <?php if ($item['size'])
                                                    echo ' · Size: ' . htmlspecialchars($item['size']); ?>
                                                · <span class="font-medium">Qty: <?= $item['quantity'] ?></span>
                                            </p>
                                        </div>
                                        <div class="shrink-0"><?= statusBadge($item['tracking_status']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ RIGHT SIDEBAR ══ -->
            <div class="space-y-5">

                <!-- Status Summary -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-layer-group text-gray-400 text-sm"></i>
                        <h3 class="font-semibold text-gray-800 text-sm">Status Summary</h3>
                    </div>
                    <div class="p-5 space-y-2 text-sm">
                        <?php if ($isReplacement): ?>
                            <div class="flex justify-between items-center py-2 border-b border-gray-50">
                                <span class="text-gray-500">Type</span>
                                <span class="flex items-center gap-1 font-medium text-orange-700">
                                    <i class="fas fa-sync-alt text-xs"></i> Replacement
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between items-center py-2 border-b border-gray-50">
                            <span class="text-gray-500">Booking Status</span>
                            <?= statusBadge($booking['booking_status']) ?>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-50">
                            <span class="text-gray-500">Order Status</span>
                            <span class="font-medium text-gray-800"><?= $booking['order_status'] ?></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-50">
                            <span class="text-gray-500">Type</span>
                            <span class="font-medium text-gray-800 capitalize"><?= $booking['booking_type'] ?></span>
                        </div>
                        <?php if ($booking['dispatcher_name']): ?>
                            <div class="flex justify-between items-center py-2">
                                <span class="text-gray-500">Dispatcher</span>
                                <span
                                    class="font-medium text-gray-800"><?= htmlspecialchars($booking['dispatcher_name']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-stream text-gray-400 text-sm"></i>
                        <h3 class="font-semibold text-gray-800 text-sm">Timeline</h3>
                    </div>
                    <div class="p-5">
                        <ol class="relative border-l border-gray-200 ml-3 space-y-5">

                            <?php
                            $events = [
                                ['label' => 'Booking Created', 'time' => $booking['created_at'], 'done' => true],
                                ['label' => 'Estimated Pickup', 'time' => $booking['estimated_pickup_time'], 'done' => (bool) $booking['estimated_pickup_time']],
                                ['label' => 'Actual Pickup', 'time' => $booking['actual_pickup_time'], 'done' => (bool) $booking['actual_pickup_time']],
                                ['label' => 'Estimated Delivery', 'time' => $booking['estimated_delivery_time'], 'done' => (bool) $booking['estimated_delivery_time']],
                                [
                                    'label' => $booking['booking_type'] === 'pickup' ? 'Picked Up' : 'Delivered',
                                    'time' => $booking['actual_delivery_time'],
                                    'done' => (bool) $booking['actual_delivery_time']
                                ],
                            ];
                            foreach ($events as $ev):
                                if (!$ev['time'])
                                    continue;
                                ?>
                                <li class="ml-5">
                                    <span
                                        class="absolute -left-2 flex items-center justify-center w-4 h-4 rounded-full <?= $ev['done'] ? 'bg-green-500' : 'bg-gray-300' ?>">
                                        <i
                                            class="fas <?= $ev['done'] ? 'fa-check' : 'fa-circle' ?> text-white text-[8px]"></i>
                                    </span>
                                    <p class="font-medium text-gray-800 text-xs"><?= $ev['label'] ?></p>
                                    <p class="text-xs text-gray-400"><?= date('M d, Y g:i A', strtotime($ev['time'])) ?></p>
                                </li>
                            <?php endforeach; ?>

                        </ol>
                    </div>
                </div>

                <!-- Customer -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-user text-gray-400 text-sm"></i>
                        <h3 class="font-semibold text-gray-800 text-sm">Customer</h3>
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Name</p>
                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($booking['customer_name']) ?>
                            </p>
                        </div>
                        <?php if ($booking['mobile']): ?>
                            <div>
                                <p class="text-xs text-gray-400 mb-0.5">Mobile</p>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($booking['mobile']) ?></p>
                            </div>
                        <?php endif; ?>
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Address</p>
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($booking['address']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-receipt text-gray-400 text-sm"></i>
                        <h3 class="font-semibold text-gray-800 text-sm">Order Summary</h3>
                    </div>
                    <div class="p-5 text-sm space-y-2">
                        <div class="flex justify-between py-1.5 border-b border-gray-50">
                            <span class="text-gray-500">Order ID</span>
                            <span class="font-semibold text-gray-900">#<?= $booking['order_id'] ?></span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-gray-50">
                            <span class="text-gray-500">Scheduled Date</span>
                            <span
                                class="font-medium text-gray-800"><?= date('M d, Y', strtotime($booking['delivery_date'])) ?></span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-gray-50">
                            <span class="text-gray-500">Scheduled Time</span>
                            <span
                                class="font-medium text-gray-800"><?= date('g:i A', strtotime($booking['delivery_time'])) ?></span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-gray-50">
                            <span class="text-gray-500">Total Items</span>
                            <span class="font-medium text-gray-800"><?= $booking['total_items'] ?></span>
                        </div>
                        <div class="flex justify-between py-1.5">
                            <span class="text-gray-500">Order Total</span>
                            <span
                                class="font-bold text-gray-900">₱<?= number_format($booking['final_total'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($booking['booking_notes']): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-sticky-note text-yellow-500 text-sm"></i>
                            <h3 class="font-semibold text-yellow-900 text-sm">Booking Notes</h3>
                        </div>
                        <p class="text-sm text-yellow-800 leading-relaxed break-words">
                            <?= nl2br(htmlspecialchars($booking['booking_notes'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($booking['delivery_notes']): ?>
                    <div class="bg-orange-50 border border-orange-200 rounded-xl p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-clipboard text-orange-500 text-sm"></i>
                            <h3 class="font-semibold text-orange-900 text-sm">Delivery Notes</h3>
                        </div>
                        <p class="text-sm text-orange-800 leading-relaxed">
                            <?= nl2br(htmlspecialchars($booking['delivery_notes'])) ?></p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imgModal" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
        onclick="closeModal()">
        <div class="relative max-w-3xl w-full" onclick="event.stopPropagation()">
            <button onclick="closeModal()"
                class="absolute -top-10 right-0 text-white/70 hover:text-white text-sm flex items-center gap-1">
                <i class="fas fa-times"></i> Close
            </button>
            <img id="modalImg" src="" alt="Full size proof" class="w-full rounded-xl shadow-2xl">
        </div>
    </div>

    <script>
        function openModal(src) {
            document.getElementById('modalImg').src = src;
            document.getElementById('imgModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            document.getElementById('imgModal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    </script>

</body>

</html>