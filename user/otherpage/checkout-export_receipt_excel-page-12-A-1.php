<?php
// export_receipt_excel.php
require ROOT_PATH . 'connection/connect.php';
require ROOT_PATH . 'vendor/autoload.php'; // PhpSpreadsheet autoloader

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/googlecallback');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;

// Validate order_id
if (!$order_id || !is_numeric($order_id)) {
    die('Invalid order ID');
}

// Fetch order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    die('Order not found');
}

$order = $order_result->fetch_assoc();
$stmt->close();

// Fetch order items
$stmt = $conn->prepare("
    SELECT 
        oi.*,
        p.id as product_id_from_products,
        p.codename as product_category,
        p.main_image as product_image,
        p.product_name as catalog_product_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? 
    ORDER BY oi.id
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$order_items = [];
while ($row = $items_result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();

// Calculate totals
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['subtotal'];
}

$delivery_fee = $order['delivery_fee'] ?? 0;
$final_total = $order['total'];
$subtotal_with_vat = $final_total - $delivery_fee;
$calculated_subtotal = $subtotal_with_vat / 1.12;
$vat_amount = $subtotal_with_vat - $calculated_subtotal;

// Create new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator("Noble Hardware & Construction Supply")
    ->setTitle("Order Receipt - " . $order['reference_no'])
    ->setSubject("Order Receipt")
    ->setDescription("Order receipt for reference number " . $order['reference_no']);

// Set column widths to auto-fit
foreach(range('A','H') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Header - Company Name with centered logo
$sheet->setCellValue('B1', 'NOBLEHOME ORDER RECEIPT');
$sheet->mergeCells('B1:H1');
$sheet->getStyle('B1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 18,
        'color' => ['rgb' => '000000']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F0F0'] // Light gray background
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);
$sheet->getRowDimension(1)->setRowHeight(50);

// Add Logo centered in cell A1
$logoPath = '../img/logo.png'; // Adjust path to your logo
if (file_exists($logoPath)) {
    $drawing = new Drawing();
    $drawing->setName('Logo');
    $drawing->setDescription('Company Logo');
    $drawing->setPath($logoPath);
    $drawing->setHeight(40); // Logo height in pixels
    $drawing->setCoordinates('A1');
    
    // Para i-center sa A1:
    // Column A width is 25 characters ≈ 175 pixels (7 pixels per character)
    // Row 1 height is 50 points ≈ 66 pixels
    $columnAWidth = 25 * 7; // ≈ 175 pixels
    $logoWidth = 40; // Assuming square logo, same as height
    
    // Center horizontally in column A
    $offsetX = ($columnAWidth - $logoWidth) / 2;
    
    // Center vertically in row 1
    $row1Height = 50 * 1.33; // Convert points to pixels (1 point ≈ 1.33 pixels)
    $logoHeight = 40;
    $offsetY = ($row1Height - $logoHeight) / 2;
    
    $drawing->setOffsetX((int)$offsetX);
    $drawing->setOffsetY((int)$offsetY);
    
    $drawing->setWorksheet($sheet);
}

// Order Info Header
$sheet->setCellValue('A2', 'Reference No:');
$sheet->setCellValue('B2', $order['reference_no']);
$sheet->setCellValue('F2', 'Order Date:');
$sheet->setCellValue('G2', date('F j, Y g:i A', strtotime($order['created_at'])));
$sheet->mergeCells('G2:H2');
$sheet->getStyle('A2:H2')->applyFromArray([
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'CCCCCC'] // Gray background
    ]
]);

// Order Status
$sheet->setCellValue('A3', 'Status:');
$sheet->setCellValue('B3', strtoupper($order['status']));
$sheet->mergeCells('B3:C3');
$sheet->getStyle('B3')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']], // Black
]);

// Empty row
$currentRow = 4;

// Customer Information Section
$sheet->setCellValue("A{$currentRow}", 'CUSTOMER INFORMATION');
$sheet->mergeCells("A{$currentRow}:C{$currentRow}");
$sheet->getStyle("A{$currentRow}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 12],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'CCCCCC'] // Gray background
    ]
]);
$currentRow++;

$sheet->setCellValue("A{$currentRow}", 'Name:');
$sheet->setCellValue("B{$currentRow}", $order['customer_name']);
$sheet->mergeCells("B{$currentRow}:C{$currentRow}");
$currentRow++;

$sheet->setCellValue("A{$currentRow}", 'Email:');
$sheet->setCellValue("B{$currentRow}", $order['email']);
$sheet->mergeCells("B{$currentRow}:C{$currentRow}");
$currentRow++;

$sheet->setCellValue("A{$currentRow}", 'Mobile:');
$sheet->setCellValue("B{$currentRow}", $order['mobile']);
$sheet->mergeCells("B{$currentRow}:C{$currentRow}");
$currentRow++;

// Delivery Information Section
$sheet->setCellValue("A{$currentRow}", 'DELIVERY ADDRESS');
$sheet->mergeCells("A{$currentRow}:C{$currentRow}");
$sheet->getStyle("A{$currentRow}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 12],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'CCCCCC'] // Gray background
    ]
]);
$currentRow++;

$sheet->setCellValue("A{$currentRow}", $order['address']);
$sheet->mergeCells("A{$currentRow}:C{$currentRow}");
$currentRow++;

$sheet->setCellValue("A{$currentRow}", 'ZIP Code:');
$sheet->setCellValue("B{$currentRow}", $order['zipcode']);
$currentRow++;

// Empty row
$currentRow++;

// Order Items Header
$sheet->setCellValue("A{$currentRow}", 'ORDER ITEMS');
$sheet->mergeCells("A{$currentRow}:H{$currentRow}");
$sheet->getStyle("A{$currentRow}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']], // Black text
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '000000'] // Black background
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$currentRow++;

// Table Headers (now with separate Size and Color columns)
$headerRow = $currentRow;
$sheet->setCellValue("A{$currentRow}", 'Product Name');
$sheet->setCellValue("B{$currentRow}", 'Category');
$sheet->setCellValue("C{$currentRow}", 'Color');
$sheet->setCellValue("D{$currentRow}", 'Size');
$sheet->setCellValue("E{$currentRow}", 'Origin');
$sheet->setCellValue("F{$currentRow}", 'Quantity');
$sheet->setCellValue("G{$currentRow}", 'Unit Price');
$sheet->setCellValue("H{$currentRow}", 'Subtotal');

$sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F0F0']
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
]);
$currentRow++;

// Add order items
$itemsStartRow = $currentRow;
foreach ($order_items as $item) {
    $sheet->setCellValue("A{$currentRow}", $item['product_name']);
    $sheet->setCellValue("B{$currentRow}", !empty($item['product_category']) ? $item['product_category'] : '-');
    $sheet->setCellValue("C{$currentRow}", !empty($item['variant_color']) ? $item['variant_color'] : '-');
    $sheet->setCellValue("D{$currentRow}", !empty($item['size']) ? $item['size'] : '-');
    $sheet->setCellValue("E{$currentRow}", $item['origin'] ?? 'N/A');
    $sheet->setCellValue("F{$currentRow}", $item['quantity']);
    $sheet->setCellValue("G{$currentRow}", '₱' . number_format($item['price'], 2));
    $sheet->setCellValue("H{$currentRow}", '₱' . number_format($item['subtotal'], 2));

    // Style the row
    $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '999999']
            ]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);

    // Center align quantity and prices
    $sheet->getStyle("B{$currentRow}:H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $currentRow++;
}

// Add borders to all item rows
$itemsEndRow = $currentRow - 1;
$sheet->getStyle("A{$headerRow}:H{$itemsEndRow}")->applyFromArray([
    'borders' => [
        'outline' => [
            'borderStyle' => Border::BORDER_MEDIUM,
            'color' => ['rgb' => '000000']
        ]
    ]
]);

// Empty row
$currentRow++;

// Order Summary
$summaryStartRow = $currentRow;
$sheet->setCellValue("G{$currentRow}", 'Subtotal:');
$sheet->setCellValue("H{$currentRow}", '₱' . number_format($subtotal, 2));
$sheet->getStyle("G{$currentRow}")->getFont()->setBold(true);
$currentRow++;

$sheet->setCellValue("G{$currentRow}", 'VAT (12%):');
$sheet->setCellValue("H{$currentRow}", '₱' . number_format($vat_amount, 2));
$sheet->getStyle("G{$currentRow}")->getFont()->setBold(true);
$currentRow++;

$sheet->setCellValue("G{$currentRow}", 'Delivery Fee:');
$sheet->setCellValue("H{$currentRow}", '₱' . number_format($delivery_fee, 2));
$sheet->getStyle("G{$currentRow}")->getFont()->setBold(true);
$currentRow++;

$sheet->setCellValue("G{$currentRow}", 'TOTAL:');
$sheet->setCellValue("H{$currentRow}", '₱' . number_format($final_total, 2));
$sheet->getStyle("G{$currentRow}:H{$currentRow}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F0F0']
    ]
]);

// Right align summary values
$sheet->getStyle("H{$summaryStartRow}:H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$currentRow++;
$currentRow++;

// Payment Method
$sheet->setCellValue("A{$currentRow}", 'Payment Method:');
$sheet->setCellValue("B{$currentRow}", $order['mode_payment']);
$sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);

$currentRow++;
$currentRow++;

// Important Notes Section
$notesStartRow = $currentRow;
$sheet->setCellValue("A{$currentRow}", 'Important Notes:');
$sheet->mergeCells("A{$currentRow}:H{$currentRow}");
$sheet->getStyle("A{$currentRow}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']], // Black text
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F0F0'] // Light gray
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
]);
$currentRow++;

// Important Notes List
$notes = [
    '• We will review your order and contact you within 24 hours',
    '• Final total includes 12% VAT and delivery fees',
    '• Please keep this receipt for your records',
    '• Product ratings will be enabled once your order is delivered',
    '• Your ratings help us improve our products and services',
    '• For questions, contact us with reference: ' . $order['reference_no']
];

foreach ($notes as $note) {
    $sheet->setCellValue("A{$currentRow}", $note);
    $sheet->mergeCells("A{$currentRow}:H{$currentRow}");
    $sheet->getStyle("A{$currentRow}")->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F0F0F0'] // Light gray
        ],
        'font' => ['color' => ['rgb' => '000000']], // Black text
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP]
    ]);
    $currentRow++;
}

// Add border to Important Notes section
$notesEndRow = $currentRow - 1;
$sheet->getStyle("A{$notesStartRow}:H{$notesEndRow}")->applyFromArray([
    'borders' => [
        'outline' => [
            'borderStyle' => Border::BORDER_MEDIUM,
            'color' => ['rgb' => '000000'] // Black border
        ]
    ]
]);

$currentRow++;

// Footer
$sheet->setCellValue("A{$currentRow}", 'Thank you for choosing our service!');
$sheet->mergeCells("A{$currentRow}:H{$currentRow}");
$sheet->getStyle("A{$currentRow}")->applyFromArray([
    'font' => ['italic' => true, 'color' => ['rgb' => '666666']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

$currentRow++;
$sheet->setCellValue("A{$currentRow}", 'Generated on ' . date('F j, Y g:i A'));
$sheet->mergeCells("A{$currentRow}:H{$currentRow}");
$sheet->getStyle("A{$currentRow}")->applyFromArray([
    'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '999999']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// Set lastRow variable
$lastRow = $currentRow;

// Apply light gray background to entire document (A1 to H32)
$sheet->getStyle("A1:H32")->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F0F0'] // Very light gray background
    ]
]);

// Apply outer border to entire document (A to H columns)
$borderStartRow = 1;
$sheet->getStyle("A{$borderStartRow}:H{$lastRow}")->applyFromArray([
    'borders' => [
        'outline' => [
            'borderStyle' => Border::BORDER_THICK,
            'color' => ['rgb' => '000000']
        ]
    ]
]);

// Set print settings
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// Set margins
$sheet->getPageMargins()->setTop(0.5);
$sheet->getPageMargins()->setRight(0.5);
$sheet->getPageMargins()->setLeft(0.5);
$sheet->getPageMargins()->setBottom(0.5);

// Set headers for download
$filename = "Order_Receipt_{$order['reference_no']}_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Create Excel file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;