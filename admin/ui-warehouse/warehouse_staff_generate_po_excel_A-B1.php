<?php
// warehouse_staff_generate_po_pdf_A-B1.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
// Start output buffering to prevent any output before headers
ob_start();


require_role(['warehouse', 'superadmin', 'sales']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ". BASE_URL ."/main");
    exit();
}

// Include DomPDF
require_once ROOT_PATH . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Allow both POST and GET requests (GET for downloads, POST for generation)
$is_download_request = isset($_GET['download_approved']) && $_GET['download_approved'] == '1';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$is_download_request) {
    header("Location: ordering.php");
    exit();
}

// Check if this is a download request for approved PO (can come from GET or POST)
$is_download_approved = (isset($_POST['download_approved']) && $_POST['download_approved'] == '1') || 
                        (isset($_GET['download_approved']) && $_GET['download_approved'] == '1');

// Function to fetch logo from database
function getCompanyLogoBlob($conn)
{
    $query = "SELECT logo_blob FROM company_logos ORDER BY created_at DESC LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (!$result || mysqli_num_rows($result) == 0) {
        return null;
    }

    $row = mysqli_fetch_assoc($result);
    return $row['logo_blob'] ?? null;
}

// Try to get logo from database first
$logo_blob = getCompanyLogoBlob($conn);

if ($logo_blob) {
    // Convert BLOB to base64 for PDF
    $base64_logo = base64_encode($logo_blob);
    $company_logo = 'data:image/png;base64,' . $base64_logo;
} else {
    // Fallback to default logo path
    $logo_file = __DIR__ . '/../../img/logo/logo.png';
    if (file_exists($logo_file)) {
        $company_logo = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_file));
    } else {
        $company_logo = null;
    }
}

// Get parameters from POST or GET (GET for downloads)
$params = $is_download_request ? $_GET : $_POST;

$order_id = intval($params['order_id']);
$supplier_key = $params['supplier_key'];
$editing_po_id = isset($params['editing_po_id']) ? (int)$params['editing_po_id'] : 0;
$payment_terms = $params['payment_terms'] ?? '';
$delivery_details = $params['delivery_details'] ?? '';

// If editing, convert supplier name to supplier ID
if ($editing_po_id > 0 && !is_numeric($supplier_key)) {
    $supplierLookupSql = "SELECT id FROM supplier_list WHERE business_name = ? LIMIT 1";
    $supplierLookupStmt = $conn->prepare($supplierLookupSql);
    $supplierLookupStmt->bind_param("s", $supplier_key);
    $supplierLookupStmt->execute();
    $supplierLookupResult = $supplierLookupStmt->get_result();
    
    if ($supplierRow = $supplierLookupResult->fetch_assoc()) {
        $supplier_key = strval($supplierRow['id']);
    }
    $supplierLookupStmt->close();
}

$conditions = $params['conditions'] ?? '';
$additional_notes = $params['additional_notes'] ?? '';

// Get the prepared_by from request data first, then fallback to session
$prepared_by = $params['prepared_by'] ?? $_SESSION['noble_name'] ?? 'Unknown User';
$user_role = $_SESSION['noble_lvl'] ?? 'Unknown Role';
$user_id = $_SESSION['noble_id'] ?? null;

// Get order details
$orderStmt = $conn->prepare("SELECT id, customer_name, email, created_at, status, total FROM orders WHERE id = ?");
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    die("Order not found");
}

// Get order items for the selected supplier
$itemStmt = $conn->prepare("
    SELECT 
        oi.id as item_id,
        oi.order_id,
        oi.product_id,
        oi.variant_id,
        oi.product_name,
        oi.size,
        oi.variant_color,
        oi.codename,
        oi.descrip6,
        oi.descrip7,
        oi.price as order_price,
        oi.quantity,
        oi.subtotal as original_subtotal,
        oi.origin,
        oi.supplier_id,
        oi.manual_supplier_name,
        oi.po_number,
        slp.supplier_price,
        COALESCE(slp.supplier_price, oi.price) as unit_price,
        (COALESCE(slp.supplier_price, oi.price) * oi.quantity) as calculated_subtotal,
        sl.business_name,
        sl.primary_contact_name,
        sl.email_address,
        sl.phone_number,
        sl.business_address,
        pv.namevariant,
        pv.color as variant_color_db,
        pv.size as variant_size_db
    FROM order_items oi
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
    LEFT JOIN supp_link_products slp ON oi.variant_id = slp.variant_id 
        AND oi.supplier_id = slp.supplier_id 
        AND slp.status = 'active'
    WHERE oi.order_id = ? AND (oi.supplier_id IS NOT NULL OR oi.manual_supplier_name IS NOT NULL)
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
        strval($item['supplier_id']) : 
        'manual_' . $item['manual_supplier_name'];
    
    if ($itemSupplierKey === $supplier_key) {
        $supplierItems[] = $item;
        if (!$supplierInfo) {
            $supplierInfo = [
                'name' => $item['supplier_id'] ? $item['business_name'] : $item['manual_supplier_name'],
                'contact' => $item['primary_contact_name'] ?? '',
                'email' => $item['email_address'] ?? '',
                'phone' => $item['phone_number'] ?? '',
                'address' => $item['business_address'] ?? '',
                'is_manual' => !$item['supplier_id']
            ];
        }
    }
}

if (empty($supplierItems)) {
    die("No items found for selected supplier. Selected key: " . $supplier_key);
}

// Generate custom P.O. number
$supplier_id_for_po = 0;
foreach ($supplierItems as $item) {
    if ($item['supplier_id']) {
        $supplier_id_for_po = $item['supplier_id'];
        break;
    }
}
$custom_po_number = 'NH' . date('mdY') . date('Gis') . $supplier_id_for_po;

// Get signatures if this is an approved download
$preparer_signature = null;
$approver_name = '';
$approver_signature = null;
$noter_name = '';
$noter_signature = null;

if ($is_download_approved && $editing_po_id > 0) {
    $approvalSql = "SELECT 
                        pa.superadmin_approved_by,
                        pa.approved_by,
                        superadmin.fullname as superadmin_name,
                        superadmin.e_signature as superadmin_signature,
                        approver.fullname as approver_name,
                        approver.e_signature as approver_signature,
                        preparer.e_signature as preparer_signature
                    FROM po_attachments pa
                    LEFT JOIN nobleaccount superadmin ON pa.superadmin_approved_by = superadmin.id
                    LEFT JOIN nobleaccount approver ON pa.approved_by = approver.id
                    LEFT JOIN nobleaccount preparer ON preparer.fullname = ?
                    WHERE pa.id = ?";
    $approvalStmt = $conn->prepare($approvalSql);
    $approvalStmt->bind_param("si", $prepared_by, $editing_po_id);
    $approvalStmt->execute();
    $approvalData = $approvalStmt->get_result()->fetch_assoc();
    $approvalStmt->close();
    
    if ($approvalData) {
        $approver_name = $approvalData['superadmin_name'] ?? '';
        $approver_signature = $approvalData['superadmin_signature'];
        $noter_name = $approvalData['approver_name'] ?? '';
        $noter_signature = $approvalData['approver_signature'];
        $preparer_signature = $approvalData['preparer_signature'];
    }
}

// Convert blob signatures to base64 for PDF
function blobToBase64($blob) {
    if (!$blob) return null;
    return 'data:image/png;base64,' . base64_encode($blob);
}

$preparer_sig_base64 = blobToBase64($preparer_signature);
$approver_sig_base64 = blobToBase64($approver_signature);
$noter_sig_base64 = blobToBase64($noter_signature);

// Calculate total
$total_amount = 0;
foreach ($supplierItems as $item) {
    $total_amount += floatval($item['calculated_subtotal']);
}

// Build HTML content for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        
        .container {
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .header h1 {
            font-size: 24pt;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .header p {
            font-size: 9pt;
            color: #666;
        }
        
        .info-section {
            margin-bottom: 15px;
            display: table;
            width: 100%;
        }
        
        .info-left, .info-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 10px;
        }
        
        .info-left {
            border-right: 1px solid #ddd;
        }
        
        .info-box {
            margin-bottom: 10px;
        }
        
        .info-box strong {
            display: block;
            color: #2c3e50;
            margin-bottom: 3px;
            font-size: 9pt;
        }
        
        .info-box p {
            margin-left: 5px;
            font-size: 9pt;
        }
        
        .po-details {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .po-details table {
            width: 100%;
        }
        
        .po-details td {
            padding: 5px;
            font-size: 9pt;
        }
        
        .po-details strong {
            color: #2c3e50;
        }
        
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        table.items-table th {
            background-color: #2c3e50;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 9pt;
            font-weight: bold;
        }
        
        table.items-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 9pt;
        }
        
        table.items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .total-section {
            text-align: right;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
        }
        
        .total-section strong {
            font-size: 12pt;
            color: #2c3e50;
        }
        
        .notes-section {
            margin-bottom: 15px;
            padding: 15px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .notes-section strong {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-size: 10pt;
        }
        
        .notes-section p {
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
        
        .signature-section {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        
        .signature-box {
            display: table-cell;
            width: 25%;
            padding: 10px;
            text-align: center;
            vertical-align: bottom;
        }
        
        .signature-box .sig-image {
            height: 50px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .signature-box .sig-image img {
            max-height: 45px;
            max-width: 100%;
        }
        
        .signature-box .sig-line {
            border-top: 1px solid #333;
            margin: 5px auto;
            width: 80%;
        }
        
        .signature-box .sig-name {
            font-size: 9pt;
            font-weight: bold;
            color: #2c3e50;
            margin-top: 3px;
        }
        
        .signature-box .sig-label {
            font-size: 8pt;
            color: #666;
            margin-bottom: 8px;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="width: 20%; text-align: left; border: none;">';
if ($company_logo) {
    $html .= '<img src="' . $company_logo . '" style="max-height: 60px; max-width: 100px;">';
}
$html .= '
                    </td>
                    <td style="width: 80%; text-align: center; border: none;">
                        <h1 style="margin: 0;">PURCHASE ORDER</h1>
                        <p style="margin: 5px 0;">NHCC - 2nd Floor, MC Premiere, Quezon City Metro Manila</p>
                        <p style="margin: 5px 0;">Mobile: +639922394563 | Email: noblehomeconst.ph@gmail.com</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="info-section">
            <div class="info-left">
                <div class="info-box">
                    <strong>SUPPLIER:</strong>
                    <p>' . htmlspecialchars($supplierInfo['name']) . '</p>
                </div>';

if (!empty($supplierInfo['contact'])) {
    $html .= '
                <div class="info-box">
                    <strong>Contact Person:</strong>
                    <p>' . htmlspecialchars($supplierInfo['contact']) . '</p>
                </div>';
}

if (!empty($supplierInfo['email'])) {
    $html .= '
                <div class="info-box">
                    <strong>Email:</strong>
                    <p>' . htmlspecialchars($supplierInfo['email']) . '</p>
                </div>';
}

if (!empty($supplierInfo['phone'])) {
    $html .= '
                <div class="info-box">
                    <strong>Phone:</strong>
                    <p>' . htmlspecialchars($supplierInfo['phone']) . '</p>
                </div>';
}

if (!empty($supplierInfo['address'])) {
    $html .= '
                <div class="info-box">
                    <strong>Address:</strong>
                    <p>' . htmlspecialchars($supplierInfo['address']) . '</p>
                </div>';
}

$html .= '
            </div>
            <div class="info-right">
                <div class="info-box">
                    <strong>P.O. Number:</strong>
                    <p>' . htmlspecialchars($custom_po_number) . '</p>
                </div>
                <div class="info-box">
                    <strong>Date:</strong>
                    <p>' . date('F d, Y') . '</p>
                </div>
                <div class="info-box">
                    <strong>Prepared By:</strong>
                    <p>' . htmlspecialchars($prepared_by) . '</p>
                </div>
            </div>
        </div>
        
        <div class="po-details">
            <table>
                <tr>
                    <td width="50%"><strong>Payment Terms:</strong> ' . htmlspecialchars($payment_terms) . '</td>
                    <td width="50%"><strong>Delivery:</strong> ' . htmlspecialchars($delivery_details) . '</td>
                </tr>
            </table>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">No.</th>
                    <th width="25%">Item Name</th>
                    <th width="25%">Specification</th>
                    <th width="8%">Unit</th>
                    <th width="8%" class="text-center">Qty</th>
                    <th width="12%" class="text-right">Unit Price</th>
                    <th width="15%" class="text-right">Total Price</th>
                </tr>
            </thead>
            <tbody>';

foreach ($supplierItems as $index => $item) {
    $productName = $item['product_name'];
    if (!empty($item['namevariant'])) {
        $productName .= ' - ' . $item['namevariant'];
    }
    
    $size = !empty($item['variant_size_db']) ? $item['variant_size_db'] : $item['size'];
    $color = !empty($item['variant_color_db']) ? $item['variant_color_db'] : $item['variant_color'];
    $specification = 'Variant ID: ' . $item['variant_id'] . ' | ' . $size . ' | ' . $color;
    if (!empty($item['descrip7'])) {
        $specification .= ' | ' . $item['descrip7'];
    }
    
    $unit = !empty($item['descrip6']) ? $item['descrip6'] : 'pcs';
    
    $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($productName) . '</td>
                    <td>' . htmlspecialchars($specification) . '</td>
                    <td>' . htmlspecialchars($unit) . '</td>
                    <td class="text-center">' . $item['quantity'] . '</td>
                    <td class="text-right">PHP ' . number_format(floatval($item['unit_price']), 2) . '</td>
                    <td class="text-right">PHP ' . number_format(floatval($item['calculated_subtotal']), 2) . '</td>
                </tr>';
}

$html .= '
            </tbody>
        </table>
        
        <div class="total-section">
            <strong>TOTAL AMOUNT: PHP ' . number_format($total_amount, 2) . '</strong>
        </div>';

if (!empty($conditions)) {
    $html .= '
        <div class="notes-section">
            <strong>Conditions and Special Instructions:</strong>
            <p>' . nl2br(htmlspecialchars($conditions)) . '</p>
        </div>';
}

if (!empty($additional_notes)) {
    $html .= '
        <div class="notes-section">
            <strong>Additional Notes:</strong>
            <p>' . nl2br(htmlspecialchars($additional_notes)) . '</p>
        </div>';
}

$html .= '
        <div class="signature-section">
            <div class="signature-box">
                <div class="sig-label">Prepared By:</div>
                <div class="sig-image">';
if ($preparer_sig_base64) {
    $html .= '<img src="' . $preparer_sig_base64 . '" alt="Signature">';
}
$html .= '
                </div>
                <div class="sig-line"></div>
                <div class="sig-name">' . htmlspecialchars($prepared_by) . '</div>
            </div>
            
            <div class="signature-box">
                <div class="sig-label">Approved By:</div>
                <div class="sig-image">';
if ($approver_sig_base64) {
    $html .= '<img src="' . $approver_sig_base64 . '" alt="Signature">';
}
$html .= '
                </div>
                <div class="sig-line"></div>
                <div class="sig-name">' . ($approver_name ?: 'Pending') . '</div>
            </div>
            
            <div class="signature-box">
                <div class="sig-label">Noted By:</div>
                <div class="sig-image">';
if ($noter_sig_base64) {
    $html .= '<img src="' . $noter_sig_base64 . '" alt="Signature">';
}
$html .= '
                </div>
                <div class="sig-line"></div>
                <div class="sig-name">' . ($noter_name ?: 'Pending') . '</div>
            </div>
            
            <div class="signature-box">
                <div class="sig-label">Received By:</div>
                <div class="sig-image"></div>
                <div class="sig-line"></div>
                <div class="sig-name">__________________</div>
            </div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Generated on ' . date('F d, Y h:i A') . '</p>
        </div>
    </div>
</body>
</html>';

try {
    // Configure DomPDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Save PDF to disk
    $upload_dir = '../../uploads/p.o_files/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate filename
    if ($is_download_approved && $editing_po_id > 0) {
        $existingFileSql = "SELECT stored_filename, file_path FROM po_attachments WHERE id = ?";
        $existingFileStmt = $conn->prepare($existingFileSql);
        $existingFileStmt->bind_param("i", $editing_po_id);
        $existingFileStmt->execute();
        $existingFileData = $existingFileStmt->get_result()->fetch_assoc();
        $existingFileStmt->close();
        
        if ($existingFileData) {
            $stored_filename = $existingFileData['stored_filename'];
            $file_path = $existingFileData['file_path'];
        } else {
            $stored_filename = 'PO_' . $custom_po_number . '_' . 
                               preg_replace('/[^A-Za-z0-9]/', '_', $supplierInfo['name']) . '_' . 
                               date('Y-m-d_H-i-s') . '_' . uniqid() . '.pdf';
            $file_path = $upload_dir . $stored_filename;
        }
    } else {
        $stored_filename = 'PO_' . $custom_po_number . '_' . 
                           preg_replace('/[^A-Za-z0-9]/', '_', $supplierInfo['name']) . '_' . 
                           date('Y-m-d_H-i-s') . '_' . uniqid() . '.pdf';
        $file_path = $upload_dir . $stored_filename;
    }
    
    // Save PDF to file
    file_put_contents($file_path, $dompdf->output());
    
    // Update database with file info
    if ($is_download_approved && $editing_po_id > 0) {
        $updateFileSql = "UPDATE po_attachments 
                          SET file_replaced = 1, 
                              file_replaced_at = NOW() 
                          WHERE id = ?";
        $updateFileStmt = $conn->prepare($updateFileSql);
        $updateFileStmt->bind_param("i", $editing_po_id);
        $updateFileStmt->execute();
        $updateFileStmt->close();
    }
    
    // Save P.O. number to order_items
    $itemIds = array_column($supplierItems, 'item_id');
    if (!empty($itemIds)) {
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $updateStmt = $conn->prepare("UPDATE order_items SET po_number = ? WHERE id IN ($placeholders)");
        
        $types = 's' . str_repeat('i', count($itemIds));
        $params = array_merge([$custom_po_number], $itemIds);
        
        $bind_names = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$updateStmt, 'bind_param'], $bind_names);
        
        $updateStmt->execute();
        $updateStmt->close();
    }
    
    // Update or insert PO record
    $sanitizedPreparedBy = preg_replace('/[^A-Za-z0-9]/', '_', $prepared_by);
    $filename = 'PO_' . $custom_po_number . '_' . 
                preg_replace('/[^A-Za-z0-9]/', '_', $supplierInfo['name']) . '_' . 
                $sanitizedPreparedBy . '.pdf';
    
    if ($editing_po_id > 0) {
        if (!$is_download_approved) {
            $getOldFileSql = "SELECT file_path FROM po_attachments WHERE id = ? AND order_id = ?";
            if ($getOldFileStmt = $conn->prepare($getOldFileSql)) {
                $getOldFileStmt->bind_param("ii", $editing_po_id, $order_id);
                $getOldFileStmt->execute();
                $oldFileResult = $getOldFileStmt->get_result();
                
                if ($oldFileRow = $oldFileResult->fetch_assoc()) {
                    $old_file_path = $oldFileRow['file_path'];
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
                $getOldFileStmt->close();
            }
        }
        
        if ($is_download_approved) {
            $updateSql = "UPDATE po_attachments 
                         SET stored_filename = ?,
                             file_path = ?,
                             original_filename = ?,
                             file_replaced = 1,
                             file_replaced_at = NOW()
                         WHERE id = ? AND order_id = ?";
            
            if ($updateStmt = $conn->prepare($updateSql)) {
                $updateStmt->bind_param("sssii", 
                    $stored_filename,
                    $file_path,
                    $filename,
                    $editing_po_id,
                    $order_id
                );
                $updateStmt->execute();
                $updateStmt->close();
            }
        } else {
            $updateSql = "UPDATE po_attachments 
                         SET stored_filename = ?,
                             file_path = ?,
                             original_filename = ?,
                             po_number = ?,
                             payment_terms = ?,
                             delivery_details = ?,
                             conditions = ?,
                             additional_notes = ?,
                             prepared_by = ?,
                             file_replaced = 1,
                             file_replaced_at = NOW(),
                             superadmin_approval_status = 'pending',
                             approval_status = 'pending',
                             superadmin_approved_by = NULL,
                             superadmin_approved_at = NULL,
                             superadmin_rejection_reason = NULL,
                             approved_by = NULL,
                             approved_at = NULL,
                             rejection_reason = NULL
                         WHERE id = ? AND order_id = ?";
            
            if ($updateStmt = $conn->prepare($updateSql)) {
                $updateStmt->bind_param("sssssssssii", 
                    $stored_filename,
                    $file_path,
                    $filename,
                    $custom_po_number,
                    $payment_terms,
                    $delivery_details,
                    $conditions,
                    $additional_notes,
                    $prepared_by,
                    $editing_po_id,
                    $order_id
                );
                $updateStmt->execute();
                $updateStmt->close();
            }
        }
    } else {
        $insertSql = "INSERT INTO po_attachments 
                      (order_id, supplier_name, original_filename, stored_filename, file_path, 
                       po_number, payment_terms, delivery_details, conditions, additional_notes, 
                       prepared_by, uploaded_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        if ($insertStmt = $conn->prepare($insertSql)) {
            $insertStmt->bind_param("issssssssss", 
                $order_id, 
                $supplierInfo['name'], 
                $filename, 
                $stored_filename, 
                $file_path,
                $custom_po_number,
                $payment_terms,
                $delivery_details,
                $conditions,
                $additional_notes,
                $prepared_by
            );
            
            if ($insertStmt->execute()) {
                $checkStatusSql = "SELECT status FROM orders WHERE id = ?";
                if ($checkStmt = $conn->prepare($checkStatusSql)) {
                    $checkStmt->bind_param("i", $order_id);
                    $checkStmt->execute();
                    $currentStatus = $checkStmt->get_result()->fetch_assoc()['status'];
                    $checkStmt->close();
                    
                    if ($currentStatus !== 'processing') {
                        $statusUpdateSql = "UPDATE orders SET status = 'processing' WHERE id = ?";
                        if ($statusStmt = $conn->prepare($statusUpdateSql)) {
                            $statusStmt->bind_param("i", $order_id);
                            $statusStmt->execute();
                            $statusStmt->close();
                        }
                    }
                }
                
                $initTrackingSql = "UPDATE order_items SET tracking_status = 'processing' 
                                   WHERE order_id = ? AND tracking_status IS NULL";
                if ($trackingStmt = $conn->prepare($initTrackingSql)) {
                    $trackingStmt->bind_param("i", $order_id);
                    $trackingStmt->execute();
                    $trackingStmt->close();
                }
                
                error_log("P.O. Generated - Order ID: $order_id, Supplier: " . $supplierInfo['name'] . 
                          ", Prepared By: $prepared_by, User Role: $user_role, P.O. Number: $custom_po_number, File: $stored_filename");
            } else {
                error_log("Failed to save P.O. attachment record: " . $insertStmt->error);
            }
            $insertStmt->close();
        }
    }
    
    // Log the P.O generation
    error_log("P.O Generated - Order ID: $order_id, Supplier: " . $supplierInfo['name'] . 
              ", Prepared By: $prepared_by, User Role: $user_role, P.O. Number: $custom_po_number, File: $stored_filename");
    
    // Clean output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Send file to browser if this is an approved download
    if ($is_download_approved && file_exists($file_path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit();
    } elseif (!$is_download_approved) {
       // ✅ Tama — router route
header("Location: " . BASE_URL . "/warehouseheadstaffpomanagement?order_id=" . $order_id . "&po_saved=1");
        exit();
    }

} catch (Exception $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    error_log("Error generating P.O.: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo "<script>
        alert('Error generating P.O.: " . addslashes($e->getMessage()) . "');
        window.close();
    </script>";
    die();
}
?>