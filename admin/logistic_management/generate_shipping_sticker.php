<?php
// generate_shipping_sticker.php
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
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    die("Invalid booking ID.");
}

// Fetch booking details
$sql = "SELECT 
    db.*,
    ds.delivery_date,
    ds.delivery_time,
    ds.delivery_notes,
    ds.id as delivery_schedule_id,
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
INNER JOIN orders o ON db.order_id = o.id
LEFT JOIN transportify_vehicle_list tv ON db.vehicle_id = tv.id
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

// Fetch items
if ($isReplacement) {
    $itemsSql = "SELECT 
        oi.id, oi.product_name, oi.variant_color, oi.size,
        oi.price, oi.warehouse_location, oi.po_number, oi.qr_code,
        rr.id as replacement_id,
        rr.replacement_quantity as quantity,
        rr.reason as replacement_reason
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

// Mark as printed in session
$_SESSION['sticker_printed_' . $booking_id] = date('Y-m-d H:i:s');

// Total items count
$totalQty = 0;
foreach ($items as $item) {
    $totalQty += $item['quantity'] ?? 1;
}

// Generate a barcode-like string for visual
$barcodeStr = 'NHD-' . str_pad($booking['order_id'], 6, '0', STR_PAD_LEFT) . '-B' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);

// Get existing print timestamp from DB (if already printed before)
$existingPrintedAt = $booking['sticker_printed_at'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Sticker - Booking #<?php echo $booking_id; ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .sticker-page { padding: 10mm !important; }
            .sticker { break-inside: avoid; page-break-inside: avoid; }
        }

        .barcode-visual {
            display: flex;
            align-items: flex-end;
            gap: 1.5px;
            height: 40px;
            padding: 0 4px;
        }

        .barcode-visual span {
            display: inline-block;
            background: #1a1a1a;
            width: 2px;
            border-radius: 1px;
        }

        .sticker {
            border: 2px solid #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .sticker-header-bar {
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashed-divider {
            border-top: 2px dashed #d1d5db;
            margin: 0 12px;
        }

        .replacement-stripe {
            background: repeating-linear-gradient(
                45deg,
                #fed7aa,
                #fed7aa 5px,
                #fff7ed 5px,
                #fff7ed 10px
            );
        }

        .barcode-text {
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
            font-size: 11px;
        }
    </style>
</head>
<body class="min-h-screen">

    <!-- Top Controls Bar -->
    <div class="no-print sticky top-0 z-50 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <a href="logistic-dispatcher-view-booking-page-12.php?booking_id=<?php echo $booking_id; ?>"
                   class="flex items-center gap-2 text-gray-600 hover:text-gray-900 font-medium text-sm bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-lg transition-colors">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <div>
                    <p class="font-bold text-gray-900 text-sm">Shipping Sticker</p>
                    <p class="text-xs text-gray-500">Booking #<?php echo $booking_id; ?> · Order #<?php echo $booking['order_id']; ?> · <?php echo count($items); ?> item(s)</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <!-- Shows if already printed before (from DB) -->
                <?php if ($existingPrintedAt): ?>
                <div class="flex items-center gap-2 bg-green-100 text-green-700 px-3 py-2 rounded-lg text-xs font-semibold">
                    <i class="fas fa-check-circle"></i>
                    Previously printed: <?php echo date('M d, Y h:i A', strtotime($existingPrintedAt)); ?>
                </div>
                <?php endif; ?>

                <!-- Shows after printing in this session -->
                <div id="printIndicator" class="hidden items-center gap-2 bg-green-100 text-green-700 px-3 py-2 rounded-lg text-xs font-semibold">
                    <i class="fas fa-check-circle"></i>
                    <span id="printTimestamp"></span>
                </div>

                <button onclick="triggerPrint()"
                    class="flex items-center gap-2 bg-indigo-700 hover:bg-indigo-800 text-white px-5 py-2 rounded-lg font-bold text-sm transition-colors shadow-md">
                    <i class="fas fa-print"></i> Print / Save PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Sticker Page -->
    <div class="sticker-page max-w-4xl mx-auto px-4 py-6">

        <!-- ═══════════════════════════════════════════════ -->
        <!--  SINGLE SHIPPING LABEL (with items inside)      -->
        <!-- ═══════════════════════════════════════════════ -->
        <div class="sticker">

            <!-- Header: Shop Name + Type Badge -->
            <div class="sticker-header-bar">
                <div class="flex items-center gap-3">
                    <div class="bg-white rounded-md p-1.5">
                        <img src="../img/logo/logo.png" alt="home" class="w-12 h-12 object-contain" />
                    </div>
                    <div>
                        <p class="text-black font-black text-base tracking-wider">NOBLE HOME</p>
                        <p class="text-black text-xs">Official Store</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($isReplacement): ?>
                        <span class="bg-orange-500 text-white text-xs font-black px-3 py-1.5 rounded-full tracking-wide uppercase">
                            ⟳ REPLACEMENT
                        </span>
                    <?php endif; ?>
                    <span class="<?php echo $booking['booking_type'] === 'delivery' ? 'bg-green-500' : 'bg-blue-500'; ?> text-white text-xs font-black px-3 py-1.5 rounded-full tracking-wide uppercase">
                        <?php echo $booking['booking_type'] === 'delivery' ? ' DELIVERY' : ' PICKUP'; ?>
                    </span>
                </div>
            </div>

            <!-- Order Meta Strip -->
            <div class="bg-gray-50 px-4 py-2 flex gap-6 text-xs flex-wrap border-b border-gray-200">
                <div>
                    <span class="text-gray-400 uppercase font-bold">Order No.</span>
                    <p class="font-semibold text-gray-900 text-sm font-mono">#<?php echo str_pad($booking['order_id'], 8, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div>
                    <span class="text-gray-400 uppercase font-bold">Booking No.</span>
                    <p class="font-semibold text-gray-900 text-sm font-mono">#<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div>
                    <span class="text-gray-400 uppercase font-bold">Date</span>
                    <p class="font-semibold text-gray-900 text-sm"><?php echo date('M d, Y', strtotime($booking['delivery_date'])); ?></p>
                </div>
                <div>
                    <span class="text-gray-400 uppercase font-bold">Items</span>
                    <p class="font-semibold text-gray-900 text-sm"><?php echo count($items); ?> item(s) · <?php echo $totalQty; ?> pcs</p>
                </div>
                <?php if ($booking['final_total'] && !$isReplacement): ?>
                <div>
                    <span class="text-gray-400 uppercase font-bold">Order Total</span>
                    <p class="font-semibold text-gray-900 text-sm">₱<?php echo number_format($booking['final_total'], 2); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Ship To / From Grid -->
            <div class="grid grid-cols-2 divide-x divide-dashed divide-gray-300">
                <!-- SHIP TO -->
                <div class="p-4">
                    <p class="text-xs text-gray-400 uppercase font-black mb-2 flex items-center gap-1">
                        <i class="fas fa-map-marker-alt text-red-500"></i> SHIP TO
                    </p>
                    <p class="font-black text-gray-900 text-base"><?php echo htmlspecialchars($booking['customer_name']); ?></p>
                    <?php if ($booking['mobile']): ?>
                        <p class="text-sm text-gray-600 mt-1 flex items-center gap-1">
                            <i class="fas fa-phone text-gray-400 text-xs"></i>
                            <?php echo htmlspecialchars($booking['mobile']); ?>
                        </p>
                    <?php endif; ?>
                    <p class="text-sm text-gray-700 mt-2 leading-relaxed font-medium">
                        <?php echo htmlspecialchars($booking['address']); ?>
                    </p>
                </div>

                <!-- SHIP FROM / DELIVERY INFO -->
                <div class="p-4">
                    <p class="text-xs text-gray-400 uppercase font-black mb-2 flex items-center gap-1">
                        <i class="fas fa-warehouse text-indigo-500"></i> SHIP FROM
                    </p>
                    <p class="font-black text-gray-900 text-sm">Noble Home Warehouse</p>
                    <p class="text-xs text-gray-500 mt-1">Logistics Department</p>

                    <?php if ($booking['courier_name'] || $booking['vehicle_type']): ?>
                    <div class="mt-3 pt-3 border-t border-dashed border-gray-200">
                        <p class="text-xs text-gray-400 uppercase font-black mb-1">Courier Info</p>
                        <?php if ($booking['courier_name']): ?>
                            <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($booking['courier_name']); ?></p>
                        <?php endif; ?>
                        <?php if ($booking['vehicle_type']): ?>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($booking['vehicle_type']); ?></p>
                        <?php endif; ?>
                        <?php if ($booking['vehicle_plate_number']): ?>
                            <span class="inline-block mt-1 bg-yellow-100 border border-yellow-300 text-yellow-800 text-xs font-black px-2 py-0.5 rounded">
                                 <?php echo htmlspecialchars($booking['vehicle_plate_number']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashed-divider"></div>

            <!-- ITEMS TABLE -->
            <div class="px-4 py-3">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-2">Items in this Shipment</p>
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-100 text-gray-500 uppercase text-left">
                            <th class="py-1.5 px-2 font-bold border border-gray-200 w-6">#</th>
                            <th class="py-1.5 px-2 font-bold border border-gray-200">Product Name</th>
                            <th class="py-1.5 px-2 font-bold border border-gray-200">Color / Variant</th>
                            <th class="py-1.5 px-2 font-bold border border-gray-200">Size</th>
                            <th class="py-1.5 px-2 font-bold border border-gray-200">Warehouse Loc.</th>
                            <th class="py-1.5 px-2 font-bold border border-gray-200">PO No.</th>
                            <?php if ($isReplacement): ?><th class="py-1.5 px-2 font-bold border border-gray-200">Reason</th><?php endif; ?>
                            <th class="py-1.5 px-2 font-bold border border-gray-200 text-center">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $item): ?>
                        <?php $qty = $item['quantity'] ?? 1; ?>
                        <tr class="<?php echo $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?> text-gray-800">
                            <td class="py-2 px-2 border border-gray-200 text-gray-400 font-mono"><?php echo $idx + 1; ?></td>
                            <td class="py-2 px-2 border border-gray-200 font-semibold"><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="py-2 px-2 border border-gray-200"><?php echo htmlspecialchars($item['variant_color'] ?? '—'); ?></td>
                            <td class="py-2 px-2 border border-gray-200"><?php echo htmlspecialchars($item['size'] ?? '—'); ?></td>
                            <td class="py-2 px-2 border border-gray-200 font-medium text-gray-700"><?php echo htmlspecialchars($item['warehouse_location'] ?? '—'); ?></td>
                            <td class="py-2 px-2 border border-gray-200 font-mono text-gray-600"><?php echo htmlspecialchars($item['po_number'] ?? '—'); ?></td>
                            <?php if ($isReplacement): ?>
                            <td class="py-2 px-2 border border-gray-200 text-orange-700 capitalize"><?php echo str_replace('_', ' ', $item['replacement_reason'] ?? '—'); ?></td>
                            <?php endif; ?>
                            <td class="py-2 px-2 border border-gray-200 text-center font-black text-gray-900"><?php echo $qty; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-100">
                            <td colspan="<?php echo $isReplacement ? 7 : 6; ?>" class="py-1.5 px-2 border border-gray-200 text-right text-gray-500 font-bold uppercase text-xs">Total Pieces</td>
                            <td class="py-1.5 px-2 border border-gray-200 text-center font-black text-gray-900"><?php echo $totalQty; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="dashed-divider"></div>

            <!-- Barcode Section -->
            <div class="flex items-center justify-between px-4 py-3">
                <div>
                    <div class="barcode-visual mb-1" id="barcodeViz">
                        <?php
                        srand($booking_id * 31 + $booking['order_id']);
                        $heights = [35,28,40,22,38,30,42,25,36,20,40,33,28,38,22,42,30,25,38,35,22,40,28,36,42,20,33,38,25,30,40,22,36,28,42,35,20,38,30,25];
                        foreach ($heights as $h) {
                            $jitter = ($h + (($booking_id % 7) * 2));
                            echo '<span style="height:' . min(42, $jitter) . 'px"></span>';
                        }
                        ?>
                    </div>
                    <p class="barcode-text text-center text-gray-700"><?php echo $barcodeStr; ?></p>
                </div>

                <?php if ($booking['tracking_number']): ?>
                <div class="text-right">
                    <p class="text-xs text-gray-400 uppercase font-bold">Tracking No.</p>
                    <p class="font-mono font-black text-sm text-indigo-800"><?php echo htmlspecialchars($booking['tracking_number']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Printed timestamp footer (visible on print) -->
            <div id="printedAtFooter" class="bg-gray-50 border-t border-dashed border-gray-200 px-4 py-2 text-xs text-gray-400 flex items-center gap-2">
                <i class="fas fa-print"></i>
                <span id="printedAtText">
                    <?php if ($existingPrintedAt): ?>
                        Last printed: <?php echo date('M d, Y h:i A', strtotime($existingPrintedAt)); ?>
                    <?php else: ?>
                        Not yet printed
                    <?php endif; ?>
                </span>
            </div>

            <!-- Driver Info Bar (if available) -->
            <?php
            $driver = $booking['booking_type'] === 'pickup' ? $booking['pickup_person_name'] : $booking['driver_name'];
            if ($driver):
            ?>
            <div class="bg-indigo-50 border-t border-indigo-100 px-4 py-2 flex items-center gap-4 text-xs">
                <span class="text-indigo-400 uppercase font-black">
                    <?php echo $booking['booking_type'] === 'pickup' ? ' Pickup By' : ' Driver'; ?>:
                </span>
                <span class="font-black text-indigo-900"><?php echo htmlspecialchars($driver); ?></span>
                <?php if ($booking['pickup_person_contact']): ?>
                    <span class="text-indigo-600">· <?php echo htmlspecialchars($booking['pickup_person_contact']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Notes (if any) -->
            <?php if ($booking['delivery_notes']): ?>
            <div class="bg-yellow-50 border-t border-dashed border-yellow-200 px-4 py-2">
                <p class="text-xs font-black text-yellow-700 uppercase mb-1">Delivery Notes:</p>
                <p class="text-xs text-yellow-800"><?php echo htmlspecialchars($booking['delivery_notes']); ?></p>
            </div>
            <?php endif; ?>

        </div>

        <!-- Bottom spacer for print -->
        <div class="h-8"></div>
    </div>

    <script>
        const bookingId = <?php echo $booking_id; ?>;

        function showPrintIndicator(timestamp) {
            const indicator = document.getElementById('printIndicator');
            const ts = document.getElementById('printTimestamp');
            ts.textContent = 'Printed: ' + timestamp;
            indicator.classList.remove('hidden');
            indicator.style.display = 'flex';
        }

        function updateStickerFooter(formattedDate) {
            const footer = document.getElementById('printedAtText');
            if (footer) {
                footer.textContent = 'Printed: ' + formattedDate;
            }
        }

        async function savePrintTimestamp() {
            try {
                const response = await fetch('log_sticker_print.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ booking_id: bookingId })
                });
                const data = await response.json();
                if (data.success) {
                    console.log('Print timestamp saved to DB:', data.printed_at);
                } else {
                    console.warn('Failed to save print timestamp:', data.message);
                }
            } catch (err) {
                console.error('Error saving print timestamp:', err);
            }
        }

        async function triggerPrint() {
            const now = new Date();
            const formatted = now.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) 
                + ' ' + now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });

            // 1. Save to DB
            await savePrintTimestamp();

            // 2. Update the sticker footer so it shows on the printed copy
            updateStickerFooter(formatted);

            // 3. Show top indicator
            showPrintIndicator(formatted);

            // 4. Trigger print
            setTimeout(() => window.print(), 300);
        }
    </script>
</body>
</html>