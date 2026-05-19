<?php
// warehouse_staff_download_po_excel.php
session_name("nobleadmin");
session_start();

// Start output buffering
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin', 'sales']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get parameters from session
if (!isset($_SESSION['po_download_params'])) {
    die("Invalid download request");
}

$params = $_SESSION['po_download_params'];
unset($_SESSION['po_download_params']); // Clear after retrieving

// Include PhpSpreadsheet
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$order_id = $params['order_id'];
$supplier_key = $params['supplier_key'];
$editing_po_id = $params['editing_po_id'];
$payment_terms = $params['payment_terms'];
$delivery_details = $params['delivery_details'];
$conditions = $params['conditions'];
$additional_notes = $params['additional_notes'];
$prepared_by = $params['prepared_by'];
$is_download_approved = true;

// Get order details
$orderStmt = $conn->prepare("SELECT id, customer_name, email, created_at, status, total FROM orders WHERE id = ?");
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    die("Order not found");
}

// Convert supplier name to supplier ID if needed
if (!is_numeric($supplier_key)) {
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

// Get order items
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
    die("No items found for selected supplier");
}

// Load template
$templatePath = '../template/p.o_templates.xlsx';
if (!file_exists($templatePath)) {
    die("Template file not found");
}

try {
    $spreadsheet = IOFactory::load($templatePath);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Fill header information
    $worksheet->setCellValue('B2', $supplierInfo['name']);
    if (!empty($supplierInfo['contact'])) $worksheet->setCellValue('C4', $supplierInfo['contact']);
    if (!empty($supplierInfo['email'])) $worksheet->setCellValue('C5', $supplierInfo['email']);
    if (!empty($supplierInfo['phone'])) $worksheet->setCellValue('C6', $supplierInfo['phone']);
    
    // Company info
    $worksheet->setCellValue('E3', 'NHCC');
    $worksheet->setCellValue('E4', 'Unit Floor MC Residence, Salcedo City Metro Manila');
    $worksheet->setCellValue('E5', 'Mobile Number: +639974894523');
    $worksheet->setCellValue('E6', 'Email: nhccbusinessmail@gmail.com');
    
    // Generate P.O. number
    $supplier_id_for_po = 0;
    foreach ($supplierItems as $item) {
        if ($item['supplier_id']) {
            $supplier_id_for_po = $item['supplier_id'];
            break;
        }
    }
    $custom_po_number = 'NH' . date('mdY') . date('Gis') . $supplier_id_for_po;
    
    $worksheet->setCellValue('A11', date('Y-m-d'));
    $worksheet->setCellValue('B11', 'P.O# ' . $custom_po_number);
    $worksheet->setCellValue('D11', $payment_terms);
    
    $startRow = 15;
    $itemCount = count($supplierItems);
    $conditionsStartRow = 16;
    $notesStartRow = 22;
    $signatureStartRow = 26;
    $additionalRowsNeeded = max(0, $itemCount - 1);
    
    if ($additionalRowsNeeded > 0) {
        $shiftAmount = $additionalRowsNeeded;
        $conditionsStartRow += $shiftAmount;
        $notesStartRow += $shiftAmount;
        $signatureStartRow += $shiftAmount;
        $worksheet->insertNewRowBefore($startRow + 1, $additionalRowsNeeded);
        
        for ($i = 1; $i <= $additionalRowsNeeded; $i++) {
            $newRowIndex = $startRow + $i;
            $worksheet->duplicateStyle(
                $worksheet->getStyle('A' . $startRow . ':H' . $startRow),
                'A' . $newRowIndex . ':H' . $newRowIndex
            );
            $worksheet->getStyle('A' . $newRowIndex . ':H' . $newRowIndex)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000']
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
            $worksheet->mergeCells('G' . $newRowIndex . ':H' . $newRowIndex);
        }
    }
    
    // Fill items
    foreach ($supplierItems as $index => $item) {
        $rowIndex = $startRow + $index;
        $worksheet->setCellValue('A' . $rowIndex, $index + 1);
        
        $productName = $item['product_name'];
        if (!empty($item['namevariant'])) {
            $productName .= ' - ' . $item['namevariant'];
        }
        $worksheet->setCellValue('B' . $rowIndex, $productName);
        
        $size = !empty($item['variant_size_db']) ? $item['variant_size_db'] : $item['size'];
        $color = !empty($item['variant_color_db']) ? $item['variant_color_db'] : $item['variant_color'];
        $specification = 'Variant ID: ' . $item['variant_id'] . ' | ' . $size . ' | ' . $color;
        if (!empty($item['descrip7'])) {
            $specification .= ' | ' . $item['descrip7'];
        }
        $worksheet->setCellValue('C' . $rowIndex, $specification);
        
        $unit = !empty($item['descrip6']) ? $item['descrip6'] : 'pcs';
        $worksheet->setCellValue('D' . $rowIndex, $unit);
        $worksheet->setCellValue('E' . $rowIndex, $item['quantity']);
        $worksheet->setCellValue('F' . $rowIndex, number_format(floatval($item['unit_price']), 2));
        $worksheet->setCellValue('G' . $rowIndex, number_format(floatval($item['calculated_subtotal']), 2));
    }
    
    // Add conditions
    if (!empty($conditions)) {
        $worksheet->setCellValue('A' . $conditionsStartRow, 'Conditions and Other Special Instructions:');
        $worksheet->mergeCells('A' . $conditionsStartRow . ':H' . $conditionsStartRow);
        $worksheet->getStyle('A' . $conditionsStartRow)->getFont()->setBold(true);
        $worksheet->setCellValue('A' . ($conditionsStartRow + 1), $conditions);
        $worksheet->mergeCells('A' . ($conditionsStartRow + 1) . ':H' . ($conditionsStartRow + 2));
        $worksheet->getStyle('A' . ($conditionsStartRow + 1))->getAlignment()->setWrapText(true);
    }
    
    // Add notes
    if (!empty($additional_notes)) {
        $worksheet->setCellValue('A' . $notesStartRow, 'Additional Notes:');
        $worksheet->mergeCells('A' . $notesStartRow . ':H' . $notesStartRow);
        $worksheet->getStyle('A' . $notesStartRow)->getFont()->setBold(true);
        $worksheet->setCellValue('A' . ($notesStartRow + 1), $additional_notes);
        $worksheet->mergeCells('A' . ($notesStartRow + 1) . ':H' . ($notesStartRow + 2));
        $worksheet->getStyle('A' . ($notesStartRow + 1))->getAlignment()->setWrapText(true);
    }
    
    // Add signature section
    $worksheet->setCellValue('A' . $signatureStartRow, 'Prepared By:');
    $worksheet->setCellValue('C' . $signatureStartRow, 'Approved By:');
    $worksheet->setCellValue('E' . $signatureStartRow, 'Noted By:');
    $worksheet->setCellValue('G' . $signatureStartRow, 'Received By:');
    
    // Get signatures
    $preparer_signature = null;
    $approver_name = '';
    $approver_signature = null;
    $noter_name = '';
    $noter_signature = null;
    
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
    
    // Add preparer name and signature
    $worksheet->setCellValue('A' . ($signatureStartRow + 2), $prepared_by);
    
    if ($preparer_signature) {
        try {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Preparer Signature');
            $drawing->setDescription('Preparer Signature');
            $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
            file_put_contents($tempFile, $preparer_signature);
            $drawing->setPath($tempFile);
            $drawing->setHeight(50);
            $drawing->setCoordinates('A' . ($signatureStartRow + 1));
            $drawing->setOffsetX(35);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($worksheet);
            register_shutdown_function(function() use ($tempFile) {
                if (file_exists($tempFile)) unlink($tempFile);
            });
        } catch (Exception $e) {
            error_log("Error adding preparer signature: " . $e->getMessage());
        }
    }
    
    // Add approver name and signature
    $worksheet->setCellValue('C' . ($signatureStartRow + 2), $approver_name ?: 'Pending');
    
    if ($approver_signature) {
        try {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Approver Signature');
            $drawing->setDescription('Approver Signature');
            $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
            file_put_contents($tempFile, $approver_signature);
            $drawing->setPath($tempFile);
            $drawing->setHeight(50);
            $drawing->setCoordinates('C' . ($signatureStartRow + 1));
            $drawing->setOffsetX(35);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($worksheet);
            register_shutdown_function(function() use ($tempFile) {
                if (file_exists($tempFile)) unlink($tempFile);
            });
        } catch (Exception $e) {
            error_log("Error adding approver signature: " . $e->getMessage());
        }
    }
    
    // Add noter name and signature
    $worksheet->setCellValue('E' . ($signatureStartRow + 2), $noter_name ?: 'Pending');
    
    if ($noter_signature) {
        try {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Noter Signature');
            $drawing->setDescription('Noter Signature');
            $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
            file_put_contents($tempFile, $noter_signature);
            $drawing->setPath($tempFile);
            $drawing->setHeight(50);
            $drawing->setCoordinates('E' . ($signatureStartRow + 1));
            $drawing->setOffsetX(35);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($worksheet);
            register_shutdown_function(function() use ($tempFile) {
                if (file_exists($tempFile)) unlink($tempFile);
            });
        } catch (Exception $e) {
            error_log("Error adding noter signature: " . $e->getMessage());
        }
    }
    
    // Auto-adjust columns
    foreach(range('A','H') as $columnID) {
        $worksheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Clean output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Generate filename
    $sanitizedPreparedBy = preg_replace('/[^A-Za-z0-9]/', '_', $prepared_by);
    $filename = 'PO_' . $custom_po_number . '_' . 
                preg_replace('/[^A-Za-z0-9]/', '_', $supplierInfo['name']) . '_' . 
                $sanitizedPreparedBy . '.xlsx';
    
    // Send file to browser
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    // Log download
    error_log("P.O. Downloaded - Order ID: $order_id, Supplier: " . $supplierInfo['name'] . ", Downloaded By: $prepared_by");
    
    exit();

} catch (Exception $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    error_log("Error downloading P.O.: " . $e->getMessage());
    die("Error generating download: " . $e->getMessage());
}
?>