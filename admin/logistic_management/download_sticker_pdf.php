<?php
// download_sticker_pdf.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$user_subrole = $_SESSION['noble_subrole'] ?? '';
if ($user_subrole !== 'dispatcher') {
    header("Location: logistic-main-dashboard-page-1.php");
    exit();
}

$dispatcher_id = $_SESSION['noble_id'];
$booking_id    = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    die("Invalid booking ID.");
}

// ── 1. Fetch booking ──────────────────────────────────────────────────────────
$sql = "SELECT 
    db.*,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.id          AS delivery_schedule_id,
    ds.item_type,
    o.customer_name,
    o.email,
    o.mobile,
    o.address,
    o.final_total,
    tv.courier_name,
    tv.vehicle_type
FROM delivery_bookings db
INNER JOIN delivery_schedules ds ON db.delivery_schedule_id = ds.id
INNER JOIN orders o              ON db.order_id = o.id
LEFT  JOIN transportify_vehicle_list tv ON db.vehicle_id = tv.id
WHERE db.id = ? AND db.dispatcher_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $dispatcher_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    die("Booking not found or not assigned to you.");
}

$isReplacement = ($booking['item_type'] === 'replacement');

// ── 2. Fetch items ────────────────────────────────────────────────────────────
if ($isReplacement) {
    $itemsSql = "SELECT 
        oi.id, oi.product_name, oi.variant_color, oi.size,
        oi.price, oi.warehouse_location, oi.po_number, oi.qr_code,
        rr.id                    AS replacement_id,
        rr.replacement_quantity  AS quantity,
        rr.reason                AS replacement_reason
    FROM order_items oi
    INNER JOIN replacement_requests rr ON oi.id = rr.order_item_id
    WHERE rr.delivery_schedule_id = ?
    ORDER BY oi.product_name";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $booking['delivery_schedule_id']);
} else {
    $itemsSql = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $booking['order_id']);
}
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// ── 3. Totals & codes ─────────────────────────────────────────────────────────
$totalQty  = array_sum(array_column($items, 'quantity')) ?: count($items);
$barcodeStr = 'NHD-' . str_pad($booking['order_id'], 6, '0', STR_PAD_LEFT)
             . '-B'  . str_pad($booking_id,          4, '0', STR_PAD_LEFT);
$printedAt  = $booking['sticker_printed_at']
            ? date('M d, Y h:i A', strtotime($booking['sticker_printed_at']))
            : date('M d, Y h:i A');

// ── 4. Load mPDF ──────────────────────────────────────────────────────────────
// Composer autoload — adjust path as needed for your project structure
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    die('mPDF not found. Run: composer require mpdf/mpdf');
}
require_once $autoload;

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_top'    => 10,
    'margin_bottom' => 10,
    'margin_left'   => 10,
    'margin_right'  => 10,
    'default_font'  => 'dejavusans',
]);

$mpdf->SetTitle('Shipping Sticker - Booking #' . $booking_id);
$mpdf->SetAuthor('Noble Home');

// ── 5. Build items table rows ─────────────────────────────────────────────────
$itemRows = '';
foreach ($items as $idx => $item) {
    $qty    = $item['quantity'] ?? 1;
    $rowBg  = ($idx % 2 === 0) ? '#ffffff' : '#f9fafb';
    $reason = $isReplacement
            ? '<td style="padding:5px 6px;border:1px solid #e5e7eb;color:#c2410c;text-transform:capitalize;">'
              . htmlspecialchars(str_replace('_', ' ', $item['replacement_reason'] ?? '—')) . '</td>'
            : '';

    $itemRows .= "
    <tr style=\"background:{$rowBg};\">
        <td style=\"padding:5px 6px;border:1px solid #e5e7eb;color:#9ca3af;font-family:monospace;\">" . ($idx + 1) . "</td>
        <td style=\"padding:5px 6px;border:1px solid #e5e7eb;font-weight:600;\">" . htmlspecialchars($item['product_name']) . "</td>
        <td style=\"padding:5px 6px;border:1px solid #e5e7eb;\">" . htmlspecialchars($item['variant_color'] ?? '—') . "</td>
        <td style=\"padding:5px 6px;border:1px solid #e5e7eb;\">" . htmlspecialchars($item['size'] ?? '—') . "</td>
        <td style=\"padding:5px 6px;border:1px solid #e5e7eb;\">" . htmlspecialchars($item['warehouse_location'] ?? '—') . "</td>
        <td style=\"padding:5px 6px;border:1px solid #e5e7eb;font-family:monospace;\">" . htmlspecialchars($item['po_number'] ?? '—') . "</td>
        {$reason}
        <td style=\"padding:5px 6px;border:1px solid #e5e7eb;text-align:center;font-weight:800;\">{$qty}</td>
    </tr>";
}

// Total row colspan
$colspan = $isReplacement ? 7 : 6;

// Replacement column header (optional)
$replacementHeader = $isReplacement
    ? '<th style="padding:5px 6px;border:1px solid #d1d5db;font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;">Reason</th>'
    : '';

// Type badge colors
$typeBadgeBg    = $booking['booking_type'] === 'delivery' ? '#22c55e' : '#3b82f6';
$typeBadgeLabel = strtoupper($booking['booking_type'] === 'delivery' ? 'DELIVERY' : 'PICKUP');

// ── 6. Courier/vehicle block ──────────────────────────────────────────────────
$courierBlock = '';
if ($booking['courier_name'] || $booking['vehicle_type']) {
    $plate = $booking['vehicle_plate_number']
           ? '<span style="display:inline-block;margin-top:3px;background:#fef9c3;border:1px solid #fde047;color:#713f12;font-size:9px;font-weight:800;padding:1px 6px;border-radius:3px;">'
             . htmlspecialchars($booking['vehicle_plate_number']) . '</span>'
           : '';
    $courierBlock = '
    <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #e5e7eb;">
        <p style="font-size:9px;color:#9ca3af;font-weight:700;text-transform:uppercase;margin:0 0 3px;">Courier Info</p>
        <p style="font-size:11px;font-weight:700;color:#1f2937;margin:0;">' . htmlspecialchars($booking['courier_name'] ?? '') . '</p>
        <p style="font-size:9px;color:#6b7280;margin:2px 0 0;">' . htmlspecialchars($booking['vehicle_type'] ?? '') . '</p>
        ' . $plate . '
    </div>';
}

// ── 7. Driver/pickup bar ──────────────────────────────────────────────────────
$driver     = $booking['booking_type'] === 'pickup'
            ? ($booking['pickup_person_name'] ?? '')
            : ($booking['driver_name'] ?? '');
$driverBar  = '';
if ($driver) {
    $label   = $booking['booking_type'] === 'pickup' ? 'Pickup By' : 'Driver';
    $contact = $booking['pickup_person_contact']
             ? ' &middot; ' . htmlspecialchars($booking['pickup_person_contact'])
             : '';
    $driverBar = '
    <div style="background:#eef2ff;border-top:1px solid #c7d2fe;padding:6px 14px;font-size:10px;">
        <span style="color:#818cf8;font-weight:700;text-transform:uppercase;">' . $label . ': </span>
        <span style="font-weight:800;color:#312e81;">' . htmlspecialchars($driver) . $contact . '</span>
    </div>';
}

// ── 8. Notes bar ──────────────────────────────────────────────────────────────
$notesBar = '';
if ($booking['delivery_notes']) {
    $notesBar = '
    <div style="background:#fefce8;border-top:1px dashed #fde047;padding:6px 14px;">
        <p style="font-size:9px;font-weight:700;color:#a16207;text-transform:uppercase;margin:0 0 3px;">Delivery Notes:</p>
        <p style="font-size:10px;color:#713f12;margin:0;">' . htmlspecialchars($booking['delivery_notes']) . '</p>
    </div>';
}

// ── 9. Order total ────────────────────────────────────────────────────────────
$totalCell = (!$isReplacement && $booking['final_total'])
           ? '<div><span style="font-size:8px;color:#9ca3af;font-weight:700;text-transform:uppercase;">Order Total</span>
              <p style="font-size:12px;font-weight:600;color:#111827;margin:2px 0 0;">&#8369;' . number_format($booking['final_total'], 2) . '</p></div>'
           : '';

// ── 10. Replacement badge ─────────────────────────────────────────────────────
$replacementBadge = $isReplacement
    ? '<span style="background:#f97316;color:#fff;font-size:9px;font-weight:800;padding:3px 8px;border-radius:20px;margin-right:6px;">&#8635; REPLACEMENT</span>'
    : '';

// ── 11. Tracking number ───────────────────────────────────────────────────────
$trackingBlock = '';
if ($booking['tracking_number']) {
    $trackingBlock = '
    <div style="text-align:right;">
        <p style="font-size:9px;color:#9ca3af;font-weight:700;text-transform:uppercase;margin:0;">Tracking No.</p>
        <p style="font-family:monospace;font-weight:800;font-size:12px;color:#3730a3;margin:2px 0 0;">'
        . htmlspecialchars($booking['tracking_number']) . '</p>
    </div>';
}

// ── 12. HTML template ─────────────────────────────────────────────────────────
ob_start();
?>
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 0; padding: 0; }
    table { border-collapse: collapse; width: 100%; }
    td, th { font-size: 10px; }
</style>

<!-- ═══ STICKER WRAPPER ═══ -->
<div style="border:1.5px solid #1a1a1a;border-radius:6px;overflow:hidden;background:#fff;">

    <!-- Header -->
    <div style="padding:10px 14px;display:flex;justify-content:space-between;align-items:center;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="50%">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="padding-right:-25px;vertical-align:middle;">
                                <!-- Logo placeholder (replace src with absolute server path if needed) -->
                                <img src="../img/logo/logo.png" width="48" height="48" style="object-fit:contain;" />
                            </td>
                            <td style="vertical-align:middle;">
                                <p style="font-weight:900;font-size:14px;letter-spacing:2px;margin:0;">NOBLE HOME</p>
                                <p style="font-size:10px;margin:2px 0 0;color:#4b5563;">Official</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td width="50%" style="text-align:right;vertical-align:middle;">
                    <?php echo $replacementBadge; ?>
                    <span style="background:<?php echo $typeBadgeBg; ?>;color:#fff;font-size:13px;font-weight:800;padding:6px 16px;border-radius:20px;"><?php echo $typeBadgeLabel; ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Order Meta Strip -->
    <div style="background:#f9fafb;padding:6px 14px;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding-right:20px;">
                    <span style="font-size:8px;color:#9ca3af;font-weight:700;text-transform:uppercase;">Order No.</span>
                    <p style="font-weight:600;font-family:monospace;font-size:12px;margin:2px 0 0;">#<?php echo str_pad($booking['order_id'],8,'0',STR_PAD_LEFT); ?></p>
                </td>
                <td style="padding-right:20px;">
                    <span style="font-size:8px;color:#9ca3af;font-weight:700;text-transform:uppercase;">Booking No.</span>
                    <p style="font-weight:600;font-family:monospace;font-size:12px;margin:2px 0 0;">#<?php echo str_pad($booking_id,6,'0',STR_PAD_LEFT); ?></p>
                </td>
                <td style="padding-right:20px;">
                    <span style="font-size:8px;color:#9ca3af;font-weight:700;text-transform:uppercase;">Date</span>
                    <p style="font-weight:600;font-size:12px;margin:2px 0 0;"><?php echo date('M d, Y', strtotime($booking['delivery_date'])); ?></p>
                </td>
                <td style="padding-right:20px;">
                    <span style="font-size:8px;color:#9ca3af;font-weight:700;text-transform:uppercase;">Items</span>
                    <p style="font-weight:600;font-size:12px;margin:2px 0 0;"><?php echo count($items); ?> item(s) &middot; <?php echo $totalQty; ?> pcs</p>
                </td>
                <td><?php echo $totalCell; ?></td>
            </tr>
        </table>
    </div>

    <!-- Ship To / Ship From -->
    <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1px dashed #d1d5db;">
        <tr>
            <!-- SHIP TO -->
            <td width="50%" style="padding:12px 14px;vertical-align:top;border-right:1px dashed #d1d5db;">
                <p style="font-size:9px;color:#9ca3af;font-weight:700;text-transform:uppercase;margin:0 0 6px;">&#128205; SHIP TO</p>
                <p style="font-weight:900;font-size:13px;margin:0;"><?php echo htmlspecialchars($booking['customer_name']); ?></p>
                <?php if (!empty($booking['mobile'])): ?>
                <p style="font-size:11px;color:#4b5563;margin:4px 0 0;">&#128222; <?php echo htmlspecialchars($booking['mobile']); ?></p>
                <?php endif; ?>
                <p style="font-size:11px;color:#374151;margin:6px 0 0;line-height:1.5;"><?php echo htmlspecialchars($booking['address']); ?></p>
            </td>
            <!-- SHIP FROM -->
            <td width="50%" style="padding:12px 14px;vertical-align:top;">
                <p style="font-size:9px;color:#9ca3af;font-weight:700;text-transform:uppercase;margin:0 0 6px;">&#127970; SHIP FROM</p>
                <p style="font-weight:900;font-size:12px;margin:0;">Noble Home Warehouse</p>
                <p style="font-size:10px;color:#6b7280;margin:3px 0 0;">Logistics Department</p>
                <?php echo $courierBlock; ?>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <div style="padding:10px 14px 6px;">
        <p style="font-size:9px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:0 0 6px;">Items in this Shipment</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f3f4f6;">
                    <th style="padding:5px 6px;border:1px solid #d1d5db;font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;width:24px;">#</th>
                    <th style="padding:5px 6px;border:1px solid #d1d5db;font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;text-align:left;">Product Name</th>
                    <th style="padding:5px 6px;border:1px solid #d1d5db;font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;text-align:left;">Color / Variant</th>
                    <th style="padding:5px 6px;border:1px solid #d1d5db;font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;text-align:left;">Size</th>
                    <th style="padding:5px 6px;border:1px solid #d1d5db;font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;text-align:left;">Warehouse Loc.</th>
                    <th style="padding:5px 6px;border:1px solid #d1d5db;font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;text-align:left;">PO No.</th>
                    <?php echo $replacementHeader; ?>
                    <th style="padding:5px 6px;border:1px solid #d1d5db;font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;text-align:center;">Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php echo $itemRows; ?>
                <tr style="background:#f3f4f6;">
                    <td colspan="<?php echo $colspan; ?>" style="padding:5px 6px;border:1px solid #d1d5db;text-align:right;font-weight:700;font-size:9px;color:#6b7280;text-transform:uppercase;">Total Pieces</td>
                    <td style="padding:5px 6px;border:1px solid #d1d5db;text-align:center;font-weight:800;color:#111827;"><?php echo $totalQty; ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Barcode + Tracking -->
    <div style="padding:8px 14px;border-top:1px dashed #d1d5db;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="vertical-align:middle;">
                    <!-- Pseudo barcode using repeating chars (mPDF-compatible) -->
                    <div style="font-family:monospace;font-size:28px;letter-spacing:-1px;color:#1a1a1a;line-height:1;">
                        &#9612;&#9613;&#9612;&#9613;&#9612;&#9613;&#9612;&#9613;&#9612;&#9612;&#9613;&#9612;&#9613;&#9613;&#9612;&#9613;&#9612;
                    </div>
                    <p style="font-family:monospace;letter-spacing:3px;font-size:10px;margin:2px 0 0;color:#374151;"><?php echo $barcodeStr; ?></p>
                </td>
                <td style="text-align:right;vertical-align:middle;">
                    <?php echo $trackingBlock; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Printed At Footer -->
    <div style="background:#f9fafb;border-top:1px dashed #e5e7eb;padding:5px 14px;">
        <p style="font-size:9px;color:#9ca3af;margin:0;">&#128424; Printed: <?php echo $printedAt; ?></p>
    </div>

    <?php echo $driverBar; ?>
    <?php echo $notesBar; ?>

</div>
<?php
$html = ob_get_clean();

// ── 13. Write HTML and output PDF ─────────────────────────────────────────────
$mpdf->WriteHTML($html);

$filename = 'shipping-sticker-booking-' . $booking_id . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
exit();
?>