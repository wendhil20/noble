<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require '../../vendor/autoload.php';
include '../role/roleaccount.php';
require_role(['sales', 'superadmin']); // allow only admin and superadmin

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Optional: Auto-logout after inactivity (e.g. 30 mins)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

if (isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];

    // Get order details
    $orderQuery = "SELECT * FROM orders WHERE id = $orderId";
    $orderResult = $conn->query($orderQuery);

    if ($orderResult && $orderResult->num_rows > 0) {
        $order = $orderResult->fetch_assoc();

        // Get order items
        $itemsQuery = "SELECT * FROM order_items WHERE order_id = $orderId";
        $itemsResult = $conn->query($itemsQuery);

        // Get logged-in user info - FIXED VERSION
        $loggedInUser = 'System Administrator'; // Default fallback

        // Get user ID from session - it's stored in 'noble_id'
        $userId = null;
        if (isset($_SESSION['noble_id'])) {
            $userId = $_SESSION['noble_id'];
        } elseif (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        } elseif (isset($_SESSION['id'])) {
            $userId = $_SESSION['id'];
        }

        if ($userId) {
            // Use prepared statement for security
            $userStmt = $conn->prepare("SELECT fullname FROM nobleaccount WHERE id = ?");
            $userStmt->bind_param("i", $userId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();

            if ($userResult && $userResult->num_rows > 0) {
                $userData = $userResult->fetch_assoc();
                // Use fullname if available, otherwise use username, otherwise keep default
                if (!empty($userData['fullname'])) {
                    $loggedInUser = $userData['fullname'];
                } elseif (!empty($userData['username'])) {
                    $loggedInUser = $userData['username'];
                }
            }
            $userStmt->close();
        }

        // Create new Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator("NobleHome Admin")
            ->setLastModifiedBy("NobleHome Admin")
            ->setTitle("NobleHome Quotation - Order #$orderId")
            ->setSubject("Order Quotation")
            ->setDescription("Professional quotation for Order #$orderId")
            ->setKeywords("quotation, noblehome, order")
            ->setCategory("Quotation");

        // NOBLEHOME LOGO AREA (Row 1-3)
        $sheet->mergeCells('A1:C3');


        // Insert logo
        $drawing = new Drawing();
        $drawing->setName('NobleHome Logo');
        $drawing->setDescription('NobleHome Logo');
        $drawing->setPath('../img/logo/logo.png'); // Adjust as needed
        $drawing->setHeight(80);

        // Set to a central column like E1 or F1 (since A1 starts too far left)
        $drawing->setCoordinates('B1');

        // Optional fine-tune centering
        $drawing->setOffsetX(80); // Try 30–100 depending on logo width
        $drawing->setWorksheet($sheet);

        // Apply border and center alignment to merged logo area
        $sheet->getStyle('A1:C3')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // INITIAL QUOTATION TITLE
        $sheet->mergeCells('D1:H4');
        $sheet->setCellValue('D1', 'INITIAL QUOTATION');

        // Style for the top-left cell (merged)
        $sheet->getStyle('D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 18,
                'color' => ['rgb' => '000000']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ]
        ]);

        // Border style to apply across the full merged area (D1:J2)
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        // Apply border to all cells in D1:J2
        foreach (range('D', 'J') as $col) {
            for ($row = 1; $row <= 2; $row++) {
                $sheet->getStyle("{$col}{$row}")->applyFromArray($borderStyle);
            }
        }

        // COMPANY INFO (Right side) - Center aligned
        $sheet->mergeCells('I1:K1');
        $sheet->mergeCells('I3:K3');
        $sheet->mergeCells('I4:K4');
        $sheet->setCellValue('I1', 'MC Premier 2F, QC, Metro Manila');
        $sheet->setCellValue('I3', 'Fb Page: Noblehome Depot');
        $sheet->setCellValue('I4', 'Mobile No: 0992-239-4563');

        // Set column width
        $sheet->getColumnDimension('J')->setWidth(45);

        // Center align company info and enable text wrapping
        $sheet->getStyle('I1:K4')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Set row heights for better appearance
        $sheet->getRowDimension('1')->setRowHeight(25);
        $sheet->getRowDimension('3')->setRowHeight(20);
        $sheet->getRowDimension('4')->setRowHeight(20);

        // NOBLEHOME DEPOT
        $sheet->mergeCells('A4:C4');
        $sheet->setCellValue('A4', 'Noblehome Depot');

        // Style for merged cell
        $sheet->getStyle('A4')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ],
        ]);

        // Explicitly apply border to each cell in the merged range
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        $sheet->getStyle('A4')->applyFromArray($borderStyle);
        $sheet->getStyle('B4')->applyFromArray($borderStyle);
        $sheet->getStyle('C4')->applyFromArray($borderStyle);

        // CLIENT INFO SECTION - Center aligned
        $sheet->mergeCells('A5:C7');
        $sheet->mergeCells('D5:H7');
        $sheet->setCellValue('A5', 'Client Name:');
        $sheet->setCellValue('D5', $order['customer_name']);
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Set column widths and center align client info
        $sheet->getColumnDimension('D5')->setWidth(25);
        $sheet->getStyle('D5:H7')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ]
        ]);

        // Add borders to client info
        $sheet->getStyle('A5:H7')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Add empty rows for better spacing
        $sheet->mergeCells('A7:C7');
        $sheet->setCellValue('A7', '');
        $sheet->setCellValue('A8', '');
        $sheet->mergeCells('D8:H9');
        $sheet->mergeCells('A8:C9');
        $sheet->setCellValue('A8', 'Project Scope:');
        $sheet->setCellValue('D8', 'Order');
        $sheet->getStyle('A8:C9')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        $sheet->getStyle('D8')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        $sheet->getStyle('A8:C9')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText'   => true // optional: wraps long text inside the cell
            ]
        ]);
        $sheet->mergeCells('I2:K2');
        // RIGHT SIDE INFO - Center aligned
        $sheet->mergeCells('J5:K5');
        $sheet->mergeCells('J6:K6');
        $sheet->mergeCells('J7:K7');
        $sheet->mergeCells('J8:K8');
        $sheet->mergeCells('J9:K9');

        $sheet->setCellValue('I5', 'Date:');
        $sheet->setCellValue('J5', date('d/m/Y', strtotime($order['created_at'])));
        $sheet->setCellValue('I6', 'Quotation No:');
        $sheet->setCellValue('J6', 'Q-' . str_pad($orderId, 4, '0', STR_PAD_LEFT));
        $sheet->setCellValue('I7', 'Contact Person:');
        $sheet->setCellValue('J7', $order['customer_name']);
        $sheet->setCellValue('I8', 'Contact No:');
        $sheet->setCellValue('J8', $order['mobile']);
        $sheet->setCellValue('I9', 'Address:');
        $sheet->setCellValue('J9', $order['address']);

        // Set column widths for right side info
        $sheet->getColumnDimension('I')->setWidth(15);
        $sheet->getColumnDimension('J')->setWidth(25);

        // Center align right side info labels
        $sheet->getStyle('I5:I9')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Center align right side info values and enable text wrapping
        $sheet->getStyle('J5:K9')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ]
        ]);

        // Set row heights for better appearance
        $sheet->getRowDimension('7')->setRowHeight(30); // Contact Person
        $sheet->getRowDimension('9')->setRowHeight(30); // Address

        // Add borders to right side info
        $sheet->getStyle('I5:K9')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // MATERIALS HEADER (Row 11)
        $sheet->mergeCells('A10:K11');
        $sheet->setCellValue('A10', 'MATERIALS & FURNITURE');
        $sheet->getStyle('A10')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'EA580C'] // Orange background
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // TABLE HEADERS (Row 12) - NOW WITH SEPARATE COLOR AND SIZE COLUMNS
        $headers = ['No.', 'Item Name', 'Specification', 'Color', 'Size', 'Unit', 'Unit Price', 'Quantity', 'Labor Cost', 'Total Amount'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];


        for ($i = 0; $i < count($headers); $i++) {
            $sheet->setCellValue($columns[$i] . '12', $headers[$i]);
        }
        // Merge and center cells J13 to K1000 (row by row)
        for ($row = 13; $row <= 1000; $row++) {
            $cellRange = "J{$row}:K{$row}";
            $sheet->mergeCells($cellRange);
            $sheet->getStyle($cellRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($cellRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
        $sheet->mergeCells('J12:K12');

        $sheet->getStyle('A12:K12')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // ITEMS DATA
        $row = 13;
        $subtotal = 0;
        $itemNo = 1;

        if ($itemsResult && $itemsResult->num_rows > 0) {
            while ($item = $itemsResult->fetch_assoc()) {
                $unitPrice = floatval($item['price']);
                $quantity = intval($item['quantity']);
                $totalAmount = $unitPrice * $quantity;
                $subtotal += $totalAmount;

                $sheet->setCellValue('A' . $row, $itemNo);
                $sheet->setCellValue('B' . $row, $item['product_name']);
                $sheet->setCellValue('C' . $row, $item['descrip6'] ?? ''); // Specification
                $sheet->setCellValue('D' . $row, $item['variant_color'] ?? ''); // Color column
                $sheet->setCellValue('E' . $row, $item['size'] ?? ''); // Size column
                $sheet->setCellValue('F' . $row, 'PCS');
                $sheet->setCellValue('G' . $row, number_format($unitPrice, 2));
                $sheet->setCellValue('H' . $row, $quantity);
                $sheet->setCellValue('I' . $row, ''); // Labor Cost (empty)
                $sheet->setCellValue('J' . $row, number_format($totalAmount, 2));


                // Merge J and K
                $sheet->mergeCells("J{$row}:K{$row}");

                // Apply styles (center, bold, border for both columns)
                $style = $sheet->getStyle("J{$row}:K{$row}");
                $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $style->getFont()->setBold(true);
                $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);



                // Style the row - ALL CENTERED
                $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);

                $row++;
                $itemNo++;
            }
        }

        // CORRECTED CALCULATION LOGIC - Following the same pattern as the order card
        // Get values from database
        $discountPercent = floatval($order['discount']);
        $shippingFee = floatval($order['shipping_fee']) ?: 0.00;
        $deliveryFee = floatval($order['delivery_fee']) ?: 0.00;

        // Calculate discount amount on subtotal
        $discountAmount = 0;
        if ($discountPercent > 0) {
            $discountAmount = $subtotal * ($discountPercent / 100);
        }

        // Subtotal after discount
        $subtotalAfterDiscount = $subtotal - $discountAmount;

        // Calculate VAT (12%) on subtotal after discount (NOT including fees)
        $vatAmount = $subtotalAfterDiscount * 0.12;

        // Final total = subtotal - discount + VAT + shipping + delivery
        $finalTotal = $subtotalAfterDiscount + $vatAmount + $shippingFee + $deliveryFee;

        // TOTALS SECTION - ALL CENTERED
        $totalsRow = $row + 1;
        $sheet->mergeCells('J17:K17');
        $sheet->mergeCells('J18:K18');
        $sheet->mergeCells('J19:K19');
        $sheet->mergeCells('J20:K20');
        $sheet->mergeCells('J21:K21');
        $sheet->mergeCells('J22:K22');

        // Subtotal
        $sheet->setCellValue('I' . $totalsRow, 'Subtotal:');
        $sheet->setCellValue('J' . $totalsRow, number_format($subtotal, 2));
        $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFD700'] // Gold background
            ],
            'font' => ['bold' => true],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Add discount row if there's a discount
        if ($discountPercent > 0) {
            $totalsRow++;
            $sheet->setCellValue('I' . $totalsRow, 'Discount (' . $discountPercent . '%):');
            $sheet->setCellValue('J' . $totalsRow, '-' . number_format($discountAmount, 2));
            $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => 'FF6B6B'] // Red background for discount
                ],
                'font' => ['bold' => true],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }

        // VAT (12%) - Applied after discount
        $totalsRow++;
        $sheet->setCellValue('I' . $totalsRow, 'VAT (12%):');
        $sheet->setCellValue('J' . $totalsRow, number_format($vatAmount, 2));
        $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFD700'] // Gold background
            ],
            'font' => ['bold' => true],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Shipping Fee - Only show if there's a shipping fee
        if ($shippingFee > 0) {
            $totalsRow++;
            $sheet->setCellValue('I' . $totalsRow, 'Shipping Fee:');
            $sheet->setCellValue('J' . $totalsRow, number_format($shippingFee, 2));
            $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => 'FFD700'] // Gold background
                ],
                'font' => ['bold' => true],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }

        // Delivery Fee - Only show if there's a delivery fee
        if ($deliveryFee > 0) {
            $totalsRow++;
            $sheet->setCellValue('I' . $totalsRow, 'Delivery Fee:');
            $sheet->setCellValue('J' . $totalsRow, number_format($deliveryFee, 2));
            $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => 'FFA500'] // Orange background for delivery
                ],
                'font' => ['bold' => true],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }

        // FINAL TOTAL
        $totalsRow++;
        $sheet->setCellValue('I' . $totalsRow, 'FINAL TOTAL:');
        $sheet->setCellValue('J' . $totalsRow, number_format($finalTotal, 2));
        $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4CAF50'] // Green background for final total
            ],
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => '']
            ],

            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Add borders around the entire quotation area
        $sheet->getStyle('A1:K' . $totalsRow)->applyFromArray([
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THICK,
                    'color' => ['rgb' => '']
                ]
            ]
        ]);

        // NOTES AND TERMS SECTION - CENTERED
        $notesRow = $totalsRow + 3;
        $sheet->setCellValue('A' . $notesRow, 'NOTES:');
        $sheet->getStyle('A' . $notesRow)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        $sheet->setCellValue('A' . ($notesRow + 1), 'TERMS:');
        $sheet->getStyle('A' . ($notesRow + 1))->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);


        $termsRow = $notesRow + 2;
        $terms = [
            '1. Payment Terms: Full payment before Delivery',
            '2. Payable to NOBLEHOME CONSTRUCTION CORP.',
            '3. Quote according to the drawing, any changes made by customers will be charged accordingly',
            '   BANK ACCOUNT NAME                                               Account No.: ',
            '   BDO Noblehome Construction Corp.                    013-238-001-657          ',
            '   AUB Noblehome Construction Corp.                    151-010-002-868          ',
            '4. VAT Inclusive',
            '5. Free delivery for NCR Area only, beyond NCR have additional fee',
            '6. This is only for quotation, for more details refer to the contact'
        ];

        foreach ($terms as $index => $term) {
            $sheet->setCellValue('A' . ($termsRow + $index), $term);
            // Center align each term
            $sheet->getStyle('A' . ($termsRow + $index))->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }

        // Add borders to notes and terms section
        $sheet->getStyle('A' . $notesRow . ':K' . ($termsRow + count($terms) - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // SIGNATURE SECTION - CENTERED
        $sigRow = $termsRow + count($terms) + 3;
        $sheet->setCellValue('A' . $sigRow, 'Thank you for giving us a chance to be at service for you. Keep safe.');
        $sheet->getStyle('A' . $sigRow)->applyFromArray([
            'font' => ['italic' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        $sigRow += 2;
        $sheet->setCellValue('A' . $sigRow, 'Prepared By:');
        $sheet->setCellValue('D' . $sigRow, 'Approved By:');
        $sheet->setCellValue('G' . $sigRow, 'Noted By:');

        // Center align signature labels
        $sheet->getStyle('A' . $sigRow . ':G' . $sigRow)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        $sigRow += 2;
        // Use the logged-in user's name for "Prepared By"
        $sheet->setCellValue('A' . $sigRow, $loggedInUser);
        $sheet->setCellValue('D' . $sigRow, 'MR. KEN YANG');
        $sheet->setCellValue('G' . $sigRow, 'Mary Grace Rivera');

        // Center align signature names
        $sheet->getStyle('A' . $sigRow . ':G' . $sigRow)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Add borders to signature section
        $sheet->getStyle('A' . ($sigRow - 2) . ':J' . $sigRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // COLUMN WIDTHS - UPDATED FOR NEW LAYOUT
        $sheet->getColumnDimension('A')->setWidth(5);   // No.
        $sheet->getColumnDimension('B')->setWidth(25);  // Item Name
        $sheet->getColumnDimension('C')->setWidth(15);  // Specification
        $sheet->getColumnDimension('D')->setWidth(12);  // Color
        $sheet->getColumnDimension('E')->setWidth(12);  // Size
        $sheet->getColumnDimension('F')->setWidth(8);   // Unit
        $sheet->getColumnDimension('G')->setWidth(12);  // Unit Price
        $sheet->getColumnDimension('H')->setWidth(10);  // Quantity
        $sheet->getColumnDimension('I')->setWidth(15);  // Labor Cost
        $sheet->getColumnDimension('J')->setWidth(15);  // Total Amount
        $sheet->getColumnDimension('K')->setWidth(25);  // Company info

        // ROW HEIGHTS
        $sheet->getRowDimension('1')->setRowHeight(25);
        $sheet->getRowDimension('11')->setRowHeight(25);
        $sheet->getRowDimension('12')->setRowHeight(20);

        // Auto-fit row heights for signature section
        for ($i = $sigRow - 2; $i <= $sigRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(20);
        }

        // Create Excel file
        $writer = new Xlsx($spreadsheet);

        // Set filename
        $filename = 'NobleHome_Quotation_Order_' . $orderId . '_' . date('Y-m-d_H-i-s') . '.xlsx';

        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1'); // IE 9
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        // Clear output buffer
        ob_clean();
        flush();

        // Save to output
        $writer->save('php://output');

        // Update last activity
        $_SESSION['last_activity'] = time();

        exit();
    } else {
        // Order not found
        echo "<script>alert('Order not found!'); window.close();</script>";
        exit();
    }
} else {
    // No order ID provided
    echo "<script>alert('No order ID provided!'); window.close();</script>";
    exit();
}
