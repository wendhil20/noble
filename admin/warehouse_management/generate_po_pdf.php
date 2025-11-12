<?php
// generate_po_excel.php
session_name("nobleadmin");
session_start();

// Start output buffering to prevent any output before headers
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin', 'sales']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Include PhpSpreadsheet
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ordering.php");
    exit();
}

$order_id = intval($_POST['order_id']);
$supplier_key = $_POST['supplier_key'];
$payment_terms = $_POST['payment_terms'] ?? '';
$delivery_details = $_POST['delivery_details'] ?? '';
$conditions = $_POST['conditions'] ?? '';
$additional_notes = $_POST['additional_notes'] ?? '';

// Get the prepared_by from POST data first, then fallback to session
$prepared_by = $_POST['prepared_by'] ?? $_SESSION['noble_name'] ?? 'Unknown User';

// Also get user role and ID for additional info if needed
$user_role = $_SESSION['noble_lvl'] ?? 'Unknown Role';
$user_id = $_SESSION['noble_id'] ?? null;

// Debug information (remove in production)
error_log("Prepared By from POST: " . ($_POST['prepared_by'] ?? 'Not set'));
error_log("Prepared By from SESSION: " . ($_SESSION['noble_name'] ?? 'Not set'));
error_log("Final Prepared By: " . $prepared_by);

// Get order details
$orderStmt = $conn->prepare("SELECT id, customer_name, email, created_at, status, total FROM orders WHERE id = ?");
$orderStmt->bind_param("i", $order_id);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    die("Order not found");
}

// Get order items for the selected supplier with original_price from product_variants
$itemStmt = $conn->prepare("
    SELECT 
        oi.id as item_id,
        oi.order_id,
        oi.product_id,
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
        sl.business_address
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
    LEFT JOIN supp_link_products slp ON oi.product_id = slp.product_id 
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
        // unit_price and calculated_subtotal are already computed in the SQL query
        
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

// Load the template
$templatePath = '../template/p.o_templates.xlsx';
if (!file_exists($templatePath)) {
    die("Template file not found: " . $templatePath);
}

try {
    // Load the template
    $spreadsheet = IOFactory::load($templatePath);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Fill in the header information
    $worksheet->setCellValue('B2', $supplierInfo['name']);
    
    if (!empty($supplierInfo['contact'])) {
        $worksheet->setCellValue('C4', $supplierInfo['contact']);
    }
    if (!empty($supplierInfo['email'])) {
        $worksheet->setCellValue('C5', $supplierInfo['email']);
    }
    if (!empty($supplierInfo['phone'])) {
        $worksheet->setCellValue('C6', $supplierInfo['phone']);
    }
    
    // Company info
$worksheet->setCellValue('E3', 'NHCC');
$worksheet->setCellValue('E4', 'Unit Floor MC Residence, Salcedo City Metro Manila');
$worksheet->setCellValue('E5', 'Mobile Number: +639974894523');
$worksheet->setCellValue('E6', 'Email: nhccbusinessmail@gmail.com');

// Generate custom P.O. number
$supplier_id_for_po = 0;
foreach ($supplierItems as $item) {
    if ($item['supplier_id']) {
        $supplier_id_for_po = $item['supplier_id'];
        break;
    }
}
$custom_po_number = 'NH' . date('mdY') . date('Gis') . $supplier_id_for_po;

// P.O Details
$worksheet->setCellValue('A11', date('Y-m-d'));
$worksheet->setCellValue('B11', 'P.O# ' . $custom_po_number);
$worksheet->setCellValue('D11', $payment_terms);
    
    // DEFINE FIXED POSITIONS FOR DIFFERENT SECTIONS
    $startRow = 15;
    $itemCount = count($supplierItems);
    
    // Fixed positions based on your template structure
    $conditionsStartRow = 16; // Fixed position where conditions should start
    $notesStartRow = 22; // Fixed position where additional notes should start  
    $signatureStartRow = 26; // Fixed position where signature section should start
    
    // Calculate how many additional rows we need beyond the template row
    $additionalRowsNeeded = max(0, $itemCount - 1);
    
    // If we need more rows, we need to shift the fixed sections down
    if ($additionalRowsNeeded > 0) {
        // Calculate how much to shift down the fixed sections
        $shiftAmount = $additionalRowsNeeded;
        
        // Adjust the fixed positions
        $conditionsStartRow += $shiftAmount;
        $notesStartRow += $shiftAmount;
        $signatureStartRow += $shiftAmount;
        
        // Insert rows after the template row (A15)
        $worksheet->insertNewRowBefore($startRow + 1, $additionalRowsNeeded);
        
        // Copy the formatting from the template row to new rows
        for ($i = 1; $i <= $additionalRowsNeeded; $i++) {
            $newRowIndex = $startRow + $i;
            
            // Copy row formatting from template row (A15)
            $worksheet->duplicateStyle(
                $worksheet->getStyle('A' . $startRow . ':H' . $startRow),
                'A' . $newRowIndex . ':H' . $newRowIndex
            );
            
            // Apply borders to the new row
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
            
            // Merge columns G and H for Total Price in the new row
            $worksheet->mergeCells('G' . $newRowIndex . ':H' . $newRowIndex);
        }
    }
    
    // Fill in all items starting from row 15
    foreach ($supplierItems as $index => $item) {
        $rowIndex = $startRow + $index;
        
        // Item No. (Column A)
        $worksheet->setCellValue('A' . $rowIndex, $index + 1);
        
        // Item Name (Column B)
        $worksheet->setCellValue('B' . $rowIndex, $item['product_name']);
        
        // Specification (Column C) - combining size and color
        $specification = $item['size'] . ' | ' . $item['variant_color'];
        if (!empty($item['descrip7'])) {
            $specification .= ' | ' . $item['descrip7'];
        }
        $worksheet->setCellValue('C' . $rowIndex, $specification);
        
        // Unit (Column D) - using descrip6 as the unit
        $unit = !empty($item['descrip6']) ? $item['descrip6'] : 'pcs';
        $worksheet->setCellValue('D' . $rowIndex, $unit);
        
        // Quantity (Column E)
        $worksheet->setCellValue('E' . $rowIndex, $item['quantity']);
        
        // Unit Price (Column F) - using supplier_price or fallback to price
        $worksheet->setCellValue('F' . $rowIndex, number_format(floatval($item['unit_price']), 2));
        
        // Total Price (Column G) - using calculated subtotal
        $worksheet->setCellValue('G' . $rowIndex, number_format(floatval($item['calculated_subtotal']), 2));
        
        // Optional: Add any additional data to Column H if needed
        // $worksheet->setCellValue('H' . $rowIndex, 'Additional Data');
    }
    
    // Add conditions section at fixed position (now adjusted for additional rows)
    if (!empty($conditions)) {
        $worksheet->setCellValue('A' . $conditionsStartRow, 'Conditions and Other Special Instructions:');
        $worksheet->mergeCells('A' . $conditionsStartRow . ':H' . $conditionsStartRow);
        $worksheet->getStyle('A' . $conditionsStartRow)->getFont()->setBold(true);
        
        $worksheet->setCellValue('A' . ($conditionsStartRow + 1), $conditions);
        $worksheet->mergeCells('A' . ($conditionsStartRow + 1) . ':H' . ($conditionsStartRow + 2));
        $worksheet->getStyle('A' . ($conditionsStartRow + 1))->getAlignment()->setWrapText(true);
    }
    
    // Add additional notes section at fixed position (now adjusted for additional rows)
    if (!empty($additional_notes)) {
        $worksheet->setCellValue('A' . $notesStartRow, 'Additional Notes:');
        $worksheet->mergeCells('A' . $notesStartRow . ':H' . $notesStartRow);
        $worksheet->getStyle('A' . $notesStartRow)->getFont()->setBold(true);
        
        $worksheet->setCellValue('A' . ($notesStartRow + 1), $additional_notes);
        $worksheet->mergeCells('A' . ($notesStartRow + 1) . ':H' . ($notesStartRow + 2));
        $worksheet->getStyle('A' . ($notesStartRow + 1))->getAlignment()->setWrapText(true);
    }
    
    // Add signature section at fixed position (now adjusted for additional rows)
    $worksheet->setCellValue('A' . $signatureStartRow, 'Prepared By:');
    $worksheet->setCellValue('C' . $signatureStartRow, 'Approved By:');
    $worksheet->setCellValue('E' . $signatureStartRow, 'Noted By:');
    $worksheet->setCellValue('G' . $signatureStartRow, 'Received By:');
    
    // Use the logged-in user's name for "Prepared By"
    $worksheet->setCellValue('A' . ($signatureStartRow + 2), $prepared_by);
    $worksheet->setCellValue('C' . ($signatureStartRow + 2), 'Ken Yang');
    $worksheet->setCellValue('E' . ($signatureStartRow + 2), 'Mary Grace Rivera');
    
    // Auto-adjust column widths for better appearance
    foreach(range('A','H') as $columnID) {
        $worksheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Clean any output buffer before sending headers
    ob_end_clean();
    
    // Generate filename with custom P.O. number
    $sanitizedPreparedBy = preg_replace('/[^A-Za-z0-9]/', '_', $prepared_by);
    $filename = 'PO_' . $custom_po_number . '_' . 
                preg_replace('/[^A-Za-z0-9]/', '_', $supplierInfo['name']) . '_' . 
                $sanitizedPreparedBy . '.xlsx';
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    header('Cache-Control: max-age=0');
    
    // Save to output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    // Save the P.O. number to order_items for all items in this P.O.
$itemIds = array_column($supplierItems, 'item_id');
if (!empty($itemIds)) {
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $updateStmt = $conn->prepare("UPDATE order_items SET po_number = ? WHERE id IN ($placeholders)");
    
    // Bind parameters: first the po_number, then all item IDs
    $types = 's' . str_repeat('i', count($itemIds));
    $params = array_merge([$custom_po_number], $itemIds);
    
    // Create references for bind_param
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

// Log the P.O generation for audit purposes
error_log("P.O Generated - Order ID: $order_id, Supplier: " . $supplierInfo['name'] . ", Prepared By: $prepared_by, User Role: $user_role, P.O. Number: $custom_po_number");

} catch (Exception $e) {
    // Clear any output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    error_log("Error generating P.O.: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return to previous page with error
    echo "<script>
        alert('Error generating P.O.: " . addslashes($e->getMessage()) . "');
        window.close();
    </script>";
    die();
}
?>