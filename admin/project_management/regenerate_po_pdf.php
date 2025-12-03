<?php
// regenerate_po_pdf.php
use Dompdf\Dompdf;
use Dompdf\Options;

session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_once '../../vendor/autoload.php';
require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_user']) || !isset($_SESSION['noble_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['noble_id'];
$input = json_decode(file_get_contents('php://input'), true);
$po_id = isset($input['po_id']) ? intval($input['po_id']) : 0;

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID']);
    exit();
}

// Get complete PO data with signatures
$stmt = $conn->prepare("
    SELECT po.*, c.company_name, c.company_address, c.logo_path,
           n.fullname as created_by,
           n2.fullname as ops_approved_by, n2.e_signature as ops_signature,
           n3.fullname as acc_approved_by, n3.e_signature as acc_signature,
           n4.fullname as doc_approved_by, n4.e_signature as doc_signature
    FROM purchase_orders po
    LEFT JOIN companies c ON po.company_id = c.id
    LEFT JOIN nobleaccount n ON po.sales_user_id = n.id
    LEFT JOIN nobleaccount n2 ON po.approved_by = n2.id
    LEFT JOIN nobleaccount n3 ON po.accounting_approved_by = n3.id
    LEFT JOIN nobleaccount n4 ON po.document_controller_approved_by = n4.id
    WHERE po.id = ? AND po.sales_user_id = ?
");
$stmt->bind_param("ii", $po_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$po_data = $result->fetch_assoc();
$stmt->close();

if (!$po_data) {
    echo json_encode(['success' => false, 'message' => 'Purchase Order not found']);
    exit();
}

// Check if all approved
$all_approved = ($po_data['status'] === 'approved' && 
                 $po_data['accounting_status'] === 'approved' && 
                 ($po_data['document_controller_status'] ?? 'pending') === 'approved');

if (!$all_approved) {
    echo json_encode(['success' => false, 'message' => 'All departments must approve before updating PDF']);
    exit();
}

// Get PO items
$items_stmt = $conn->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id ASC");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$cart_items = [];
while ($row = $items_result->fetch_assoc()) {
    $cart_items[] = [
        'name' => $row['product_name'],
        'colorName' => $row['color_name'],
        'size' => $row['size'],
        'quantity' => $row['quantity'],
        'price' => $row['unit_price']
    ];
}
$items_stmt->close();

// Function to get logo
function getCompanyLogoBlob($conn, $company_id) {
    $query = "SELECT logo_blob FROM company_logos WHERE id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['logo_blob'] ?? null;
}

$logo_blob = getCompanyLogoBlob($conn, $po_data['company_id']);
if ($logo_blob) {
    $absolute_logo_path = 'data:image/png;base64,' . base64_encode($logo_blob);
} else {
    $absolute_logo_path = __DIR__ . '/../../img/logo/logo.png';
}

// Generate new PDF with signatures
try {
    $pdfContent = createQuotationPDFWithSignatures(
        $po_data['company_name'],
        $po_data['po_number'],
        $po_data['po_date'],
        $cart_items,
        $po_data['created_by'],
        $po_data['company_address'],
        $absolute_logo_path,
        $po_data['ship_to'],
        $po_data['target_delivery_date'],
        $po_data['payment_terms'],
        $po_data['project_scope'],
        // Approval data
        [
            'ops' => [
                'name' => $po_data['ops_approved_by'],
                'signature' => $po_data['ops_signature'],
                'date' => $po_data['approved_at']
            ],
            'acc' => [
                'name' => $po_data['acc_approved_by'],
                'signature' => $po_data['acc_signature'],
                'date' => $po_data['accounting_approved_at']
            ],
            'doc' => [
                'name' => $po_data['doc_approved_by'],
                'signature' => $po_data['doc_signature'],
                'date' => $po_data['document_controller_approved_at']
            ]
        ]
    );

    // Save the updated PDF
    $upload_dir = __DIR__ . '/../../uploads/purchase_orders/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Delete old PDF if exists
    if (!empty($po_data['attachment_path']) && file_exists($po_data['attachment_path'])) {
        @unlink($po_data['attachment_path']);
    }

    // Generate new filename
    $new_filename = 'po_' . $po_data['company_id'] . '_' . time() . '_approved.pdf';
    $full_path = $upload_dir . $new_filename;
    $attachment_path = '../../uploads/purchase_orders/' . $new_filename;

    if (file_put_contents($full_path, $pdfContent)) {
        // Update database with new PDF path
        $update_stmt = $conn->prepare("UPDATE purchase_orders SET attachment_path = ? WHERE id = ?");
        $update_stmt->bind_param("si", $attachment_path, $po_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'PDF updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
        $update_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save PDF file']);
    }

} catch (Exception $e) {
    error_log("PDF Generation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error generating PDF: ' . $e->getMessage()]);
}

// Function to create PDF with approval signatures
function createQuotationPDFWithSignatures($company_name, $po_number, $po_date, $cartItems, $username, $company_address = '', $logo_path = '', $ship_to = '', $target_delivery = '', $payment_terms = '', $project_scope = '', $approvals = [])
{
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isFontSubsettingEnabled', true);
    $dompdf = new Dompdf($options);

    $logoImg = '<img src="' . $logo_path . '" style="height: 60px; width: auto; background: white; padding: 5px; border-radius: 4px;">';

    $addressLine = '';
    if (!empty($company_address)) {
        $addressLines = explode("\n", $company_address);
        $addressLine = htmlspecialchars(substr($addressLines[0], 0, 50));
    }

    // Build HTML content
    $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            font-size: 11px;
            line-height: 1.6;
            color: #333;
        }

        .header {
            color: white;
            padding: 20px;
            margin-bottom: 20px;
            border-bottom: 3px solid #000000ff;
        }

        .header-top {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .header-left {
            display: table-cell;
            width: 85%;
            vertical-align: middle;
        }

        .header-right {
            display: table-cell;
            width: 15%;
            vertical-align: middle;
            text-align: right;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .logo img {
            max-width: 100%;
            max-height: 100%;
        }

        .company-info {
            flex: 1;
            color: #000000ff;
        }

        .company-info h1 {
            font-size: 20px;
            margin: 0;
            font-weight: bold;
        }

        .company-info p {
            font-size: 9px;
            margin: 2px 0;
            opacity: 0.95;
        }

        .po-badge {
            color: #000000ff;
            padding: 10px 20px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            min-width: 120px;
            flex-shrink: 0;
        }

        .info-section {
            margin-bottom: 20px;
        }

        .info-row {
            width: 100%;
            margin-bottom: 15px;
            display: table;
            table-layout: fixed;
            border-spacing: 15px 0;
        }

        .info-box {
            display: table-cell;
            width: 33.33%;
            background: #f9f9f9;
            padding: 12px;
            border-radius: 2px;
            vertical-align: top;
        }

        .info-label {
            font-weight: bold;
            font-size: 8px;
            color: #2c5282;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 10px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        thead {
            background: #000000ff;
            color: white;
        }

        th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            border: 1px solid #fcfcfcff;
        }

        td {
            padding: 10px;
            border: 1px solid #ddd;
            font-size: 10px;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .amount-cell {
            text-align: right;
            font-weight: bold;
            color: #000000ff;
        }

        .center-cell {
            text-align: center;
        }

        .totals {
            margin: 20px 0;
            width: 50%;
            margin-left: auto;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border: 1px solid #ddd;
            border-bottom: none;
            font-size: 10px;
        }

        .total-row:last-child {
            border-bottom: 1px solid #ddd;
        }

        .total-label {
            font-weight: bold;
        }

        .total-value {
            font-weight: bold;
            text-align: right;
        }

        .total-row.grand {
            background: #020202ff;
            color: white;
            font-size: 11px;
            padding: 12px 10px;
        }

        .total-row.grand .total-label,
        .total-row.grand .total-value {
            color: white;
        }

        .approval-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #000000ff;
        }

        .approval-section h3 {
            font-size: 14px;
            font-weight: bold;
            color: #000000ff;
            margin-bottom: 20px;
            text-align: center;
        }

        .approval-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-spacing: 20px 0;
        }

        .approval-box {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            background: #fafafa;
        }

        .signature-container {
            height: 60px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 2px solid #000000ff;
        }

        .signature-container img {
            max-height: 55px;
            max-width: 100%;
            object-fit: contain;
        }

        .approver-name {
            font-weight: bold;
            font-size: 11px;
            color: #000000ff;
            margin-bottom: 3px;
        }

        .approver-role {
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .approval-date {
            font-size: 8px;
            color: #999;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8px;
            color: #666;
        }

        .approved-stamp {
            position: absolute;
            top: 150px;
            right: 50px;
            background: #22c55e;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            transform: rotate(15deg);
            border: 3px solid #16a34a;
            opacity: 0.9;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div class="header-top">
            <div class="header-left" style="display: table-cell; vertical-align: middle;">
                <div style="display: table;">
                    <div class="logo" style="display: table-cell; vertical-align: middle; padding-right: 15px;">' . $logoImg . '</div>
                    <div class="company-info" style="display: table-cell; vertical-align: middle;">
                        <h1>NOBLE HOME</h1>
                        <p>Construction</p>
                        <p>' . htmlspecialchars($company_name) . '</p>
                    </div>
                </div>
            </div>
            <div class="header-right">
                <div class="po-badge">PURCHASE<br>ORDER</div>
            </div>
        </div>
    </div>

    <!-- Project Scope Section -->
    <div style="margin-bottom: 15px; padding: 10px 0; border-bottom: 1px solid #e0e0e0;">
        <div style="font-weight: bold; font-size: 8px; color: #2c5282; text-transform: uppercase; margin-bottom: 4px;">
            Project Scope
        </div>
        <div style="font-size: 10px; color: #333; line-height: 1.5;">
            ' . nl2br(htmlspecialchars($project_scope)) . '
        </div>
    </div>

    <!-- Info Boxes -->
    <div class="info-section">
        <div class="info-row">
            <div class="info-box">
                <div class="info-label">Client</div>
                <div class="info-value">' . htmlspecialchars($company_name) . '</div>
            </div>
            <div class="info-box alt">
                <div class="info-label">PO Number</div>
                <div class="info-value">' . htmlspecialchars($po_number) . '</div>
            </div>
            <div class="info-box">
                <div class="info-label">Issue Date</div>
                <div class="info-value">' . date('F d, Y', strtotime($po_date)) . '</div>
            </div>
        </div>
        <div class="info-row">
            <div class="info-box alt">
                <div class="info-label">Ship To</div>
                <div class="info-value">' . htmlspecialchars(substr($ship_to, 0, 100)) . '</div>
            </div>
            <div class="info-box">
                <div class="info-label">Target Delivery</div>
                <div class="info-value">' . date('F d, Y', strtotime($target_delivery)) . '</div>
            </div>
            <div class="info-box alt">
                <div class="info-label">Created By</div>
                <div class="info-value">' . htmlspecialchars($username) . '</div>
            </div>
        </div>
        <div class="info-row">
            <div class="info-box" style="width: 100%;">
                <div class="info-label">Payment Terms</div>
                <div class="info-value">' . htmlspecialchars($payment_terms) . '</div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">Item</th>
                <th style="width: 30%;">Description</th>
                <th style="width: 12%;">Color</th>
                <th style="width: 10%;">Size</th>
                <th style="width: 8%;">Qty</th>
                <th style="width: 15%;">Unit Price</th>
                <th style="width: 20%;">Amount</th>
            </tr>
        </thead>
        <tbody>';

    $grandTotal = 0;
    $itemNum = 1;

    foreach ($cartItems as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $grandTotal += $subtotal;

        $html .= '
            <tr>
                <td class="center-cell">' . $itemNum . '</td>
                <td>' . htmlspecialchars(substr($item['name'], 0, 35)) . '</td>
                <td>' . htmlspecialchars(substr($item['colorName'], 0, 15)) . '</td>
                <td class="center-cell">' . htmlspecialchars($item['size']) . '</td>
                <td class="center-cell">' . $item['quantity'] . '</td>
                <td class="amount-cell">P ' . number_format($item['price'], 2) . '</td>
                <td class="amount-cell">P ' . number_format($subtotal, 2) . '</td>
            </tr>';

        $itemNum++;
    }

    $vatAmount = $grandTotal * 0.12;
    $generalRequirements = $grandTotal * 0.10;
    $totalWithVat = $grandTotal + $vatAmount + $generalRequirements;

    $html .= '
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
        <div class="total-row">
            <span class="total-label">Subtotal</span>
            <span class="total-value">P ' . number_format($grandTotal, 2) . '</span>
        </div>
        <div class="total-row">
            <span class="total-label">VAT (12%)</span>
            <span class="total-value">P ' . number_format($vatAmount, 2) . '</span>
        </div>
        <div class="total-row">
            <span class="total-label">General Requirements (10%)</span>
            <span class="total-value">P ' . number_format($generalRequirements, 2) . '</span>
        </div>
        <div class="total-row grand">
            <span class="total-label">TOTAL AMOUNT DUE</span>
            <span class="total-value">P ' . number_format($totalWithVat, 2) . '</span>
        </div>
    </div>

    <!-- Approval Signatures Section -->
    <div class="approval-section">
        <h3>APPROVED BY</h3>
        <div class="approval-grid">
            <!-- Operations Approval -->
            <div class="approval-box">
                <div class="signature-container">';
    
    if (!empty($approvals['ops']['signature'])) {
        $html .= '<img src="data:image/png;base64,' . base64_encode($approvals['ops']['signature']) . '" alt="Signature">';
    }
    
    $html .= '
                </div>
                <div class="approver-name">' . htmlspecialchars($approvals['ops']['name']) . '</div>
                <div class="approver-role">Operations</div>
                <div class="approval-date">' . date('M j, Y g:i A', strtotime($approvals['ops']['date'])) . '</div>
            </div>

            <!-- Accounting Approval -->
            <div class="approval-box">
                <div class="signature-container">';
    
    if (!empty($approvals['acc']['signature'])) {
        $html .= '<img src="data:image/png;base64,' . base64_encode($approvals['acc']['signature']) . '" alt="Signature">';
    }
    
    $html .= '
                </div>
                <div class="approver-name">' . htmlspecialchars($approvals['acc']['name']) . '</div>
                <div class="approver-role">Accounting</div>
                <div class="approval-date">' . date('M j, Y g:i A', strtotime($approvals['acc']['date'])) . '</div>
            </div>

            <!-- Document Controller Approval -->
            <div class="approval-box">
                <div class="signature-container">';
    
    if (!empty($approvals['doc']['signature'])) {
        $html .= '<img src="data:image/png;base64,' . base64_encode($approvals['doc']['signature']) . '" alt="Signature">';
    }
    
    $html .= '
                </div>
                <div class="approver-name">' . htmlspecialchars($approvals['doc']['name']) . '</div>
                <div class="approver-role">Document Controller</div>
                <div class="approval-date">' . date('M j, Y g:i A', strtotime($approvals['doc']['date'])) . '</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p><strong>This is an officially approved Purchase Order with authorized signatures.</strong></p>
        <p>For inquiries, please contact us.</p>
    </div>
</body>
</html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');

    try {
        $dompdf->render();
        return $dompdf->output();
    } catch (Exception $e) {
        error_log("DOMPDF Error: " . $e->getMessage());
        throw $e;
    }
}
?>