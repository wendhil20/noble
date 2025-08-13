<?php
//generate_po_pdf.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if (!isset($_POST['order_id']) || !isset($_POST['supplier_key'])) {
    header("Location: ordering.php");
    exit();
}

$order_id = intval($_POST['order_id']);
$supplier_key = $_POST['supplier_key'];
$payment_terms = $_POST['payment_terms'] ?? '';
$delivery_details = $_POST['delivery_details'] ?? '';
$conditions = $_POST['conditions'] ?? '';
$additional_notes = $_POST['additional_notes'] ?? '';
$prepared_by = $_POST['prepared_by'] ?? 'Unknown User';

// Get the specific order
$orderStmt = $conn->prepare("
    SELECT id, customer_name, email, created_at, status, total 
    FROM orders 
    WHERE id = ? 
    LIMIT 1
");
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

// Get order items for the selected supplier
$itemStmt = $conn->prepare("
    SELECT 
        oi.id as item_id,
        oi.product_name,
        oi.size,
        oi.variant_color,
        oi.codename,
        oi.price,
        oi.quantity,
        oi.subtotal,
        oi.supplier_id,
        oi.manual_supplier_name,
        sl.business_name,
        sl.primary_contact_name,
        sl.email_address,
        sl.phone_number,
        sl.business_address
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();
$allItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

// Filter items for selected supplier
$supplierItems = [];
$supplierInfo = null;

foreach ($allItems as $item) {
    $itemSupplierKey = $item['supplier_id'] ? 
        $item['supplier_id'] : 
        'manual_' . $item['manual_supplier_name'];
    
    if ($itemSupplierKey == $supplier_key) {
        $supplierItems[] = $item;
        if (!$supplierInfo) {
            $supplierInfo = [
                'name' => $item['supplier_id'] ? $item['business_name'] : $item['manual_supplier_name'],
                'contact' => $item['primary_contact_name'] ?? '',
                'email' => $item['email_address'] ?? '',
                'phone' => $item['phone_number'] ?? '',
                'address' => $item['address'] ?? '',
                'is_manual' => !$item['supplier_id']
            ];
        }
    }
}

if (empty($supplierItems)) {
    header("Location: generate_po.php?order_id=" . $order_id);
    exit();
}

// Generate P.O. Number
$po_number = 'NHCC-' . str_pad($order_id, 4, '0', STR_PAD_LEFT) . date('Y') . '-' . substr($supplier_key, -3);
$po_date = date('n/j/Y');

// Calculate total
$total = 0;
foreach ($supplierItems as $item) {
    $total += $item['subtotal'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - <?php echo $po_number; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        
        .po-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background-color: #f97316;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
        }
        
        .logo {
            width: 40px;
            height: 40px;
            background-color: white;
            color: #f97316;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            border-radius: 5px;
        }
        
        .details-section {
            padding: 20px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-box {
            border: 2px solid #e5e7eb;
        }
        
        .detail-header {
            background-color: #f97316;
            color: white;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .detail-content {
            padding: 12px;
            min-height: 60px;
        }
        
        .po-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
        }
        
        .po-info-item {
            text-align: center;
        }
        
        .po-info-label {
            font-weight: bold;
            color: #c2410c;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .po-info-value {
            font-size: 14px;
            color: #1f2937;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background-color: #f97316;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
        }
        
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
            vertical-align: top;
        }
        
        .items-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .total-row {
            background-color: #fff7ed !important;
            font-weight: bold;
        }
        
        .notes-section {
            padding: 0 20px 20px;
        }
        
        .notes-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .notes-box {
            border: 1px solid #e5e7eb;
        }
        
        .notes-header {
            background-color: #f97316;
            color: white;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 12px;
        }
        
        .notes-content {
            padding: 12px;
            min-height: 40px;
            font-size: 11px;
            line-height: 1.4;
        }
        
        .signature-section {
            padding: 20px;
            border-top: 2px solid #e5e7eb;
        }
        
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 20px;
            text-align: center;
        }
        
        .signature-box {
            border-top: 1px solid #374151;
            padding-top: 8px;
            margin-top: 40px;
        }
        
        .signature-label {
            font-size: 11px;
            color: #6b7280;
            font-weight: bold;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #10b981;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .print-button:hover {
            background-color: #059669;
        }
        
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            
            .print-button {
                display: none;
            }
            
            .po-container {
                box-shadow: none;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">
        🖨️ Print P.O.
    </button>

    <div class="po-container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <div class="logo">NH</div>
                <h1>Purchase Order</h1>
            </div>
        </div>

        <!-- Details Section -->
        <div class="details-section">
            <!-- TO and Purchased By -->
            <div class="details-grid">
                <div class="detail-box">
                    <div class="detail-header">TO:</div>
                    <div class="detail-content">
                        <strong><?php echo htmlspecialchars($supplierInfo['name']); ?></strong><br>
                        <?php if ($supplierInfo['contact']): ?>
                            <?php echo htmlspecialchars($supplierInfo['contact']); ?><br>
                        <?php endif; ?>
                        <?php if ($supplierInfo['address']): ?>
                            <?php echo htmlspecialchars($supplierInfo['address']); ?><br>
                        <?php endif; ?>
                        <?php if ($supplierInfo['phone']): ?>
                            Phone: <?php echo htmlspecialchars($supplierInfo['phone']); ?><br>
                        <?php endif; ?>
                        <?php if ($supplierInfo['email']): ?>
                            Email: <?php echo htmlspecialchars($supplierInfo['email']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-box">
                    <div class="detail-header">Purchased By:</div>
                    <div class="detail-content">
                        <strong>NHCC</strong><br>
                        2nd Floor, MC Premiere, Quezon City Metro Manila<br>
                        Mobile Number: +63971-3355665<br>
                        E-mail: noblehomecoact@gmail.com
                    </div>
                </div>
            </div>

            <!-- P.O Info -->
            <div class="po-info">
                <div class="po-info-item">
                    <div class="po-info-label">P.O Date</div>
                    <div class="po-info-value"><?php echo $po_date; ?></div>
                </div>
                <div class="po-info-item">
                    <div class="po-info-label">P.O Number</div>
                    <div class="po-info-value"><?php echo $po_number; ?></div>
                </div>
                <div class="po-info-item">
                    <div class="po-info-label">Payment Terms</div>
                    <div class="po-info-value"><?php echo htmlspecialchars($payment_terms ?: 'As agreed'); ?></div>
                </div>
            </div>

            <!-- Delivery Details -->
            <?php if ($delivery_details): ?>
            <div class="po-info">
                <div class="po-info-item" style="grid-column: 1 / -1;">
                    <div class="po-info-label">Delivery Details</div>
                    <div class="po-info-value"><?php echo htmlspecialchars($delivery_details); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">Item No.</th>
                        <th style="width: 25%;">Item Name</th>
                        <th style="width: 25%;">Specification</th>
                        <th style="width: 8%;">Unit</th>
                        <th style="width: 10%;">Quantity</th>
                        <th style="width: 12%;">Unit Price</th>
                        <th style="width: 12%;">Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($supplierItems as $index => $item): ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($item['codename']); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($item['size']); ?><br>
                            <small><?php echo htmlspecialchars($item['variant_color']); ?></small>
                        </td>
                        <td class="text-center">pcs</td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-right">₱<?php echo number_format($item['price'], 2); ?></td>
                        <td class="text-right">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Empty rows for spacing -->
                    <?php for ($i = count($supplierItems); $i < 8; $i++): ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php endfor; ?>
                    
                    <!-- Total Row -->
                    <tr class="total-row">
                        <td colspan="6" class="text-right" style="font-weight: bold; font-size: 14px;">TOTAL:</td>
                        <td class="text-right" style="font-weight: bold; font-size: 14px;">₱<?php echo number_format($total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Notes Section -->
        <div class="notes-section">
            <div class="notes-grid">
                <?php if ($conditions): ?>
                <div class="notes-box">
                    <div class="notes-header">Condition and Other Special Instruction</div>
                    <div class="notes-content"><?php echo nl2br(htmlspecialchars($conditions)); ?></div>
                </div>
                <?php else: ?>
                <div class="notes-box">
                    <div class="notes-header">Condition and Other Special Instruction</div>
                    <div class="notes-content">&nbsp;</div>
                </div>
                <?php endif; ?>

                <?php if ($additional_notes): ?>
                <div class="notes-box">
                    <div class="notes-header">Additional Notes:</div>
                    <div class="notes-content"><?php echo nl2br(htmlspecialchars($additional_notes)); ?></div>
                </div>
                <?php else: ?>
                <div class="notes-box">
                    <div class="notes-header">Additional Notes:</div>
                    <div class="notes-content">&nbsp;</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-grid">
                <div>
                    <div class="signature-box">
                        <div class="signature-label">Prepared By:</div>
                        <div style="font-weight: bold; margin-top: 5px;"><?php echo htmlspecialchars($prepared_by); ?></div>
                    </div>
                </div>
                <div>
                    <div class="signature-box">
                        <div class="signature-label">Approved By:</div>
                    </div>
                </div>
                <div>
                    <div class="signature-box">
                        <div class="signature-label">Noted By:</div>
                        <div style="font-weight: bold; margin-top: 5px;">Mary Grace Rivera</div>
                    </div>
                </div>
                <div>
                    <div class="signature-box">
                        <div class="signature-label">Received By:</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-print dialog when page loads (optional)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 1000);
        // };
    </script>
</body>
</html>