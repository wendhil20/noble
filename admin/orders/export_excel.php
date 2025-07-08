<?php

session_start();
include '../../connection/connect.php';

// Install PhpSpreadsheet via Composer: composer require phpoffice/phpspreadsheet
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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
        
        // Create new Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator("NobleHome Admin")
            ->setLastModifiedBy("NobleHome Admin")
            ->setTitle("Order #$orderId")
            ->setSubject("Order Details")
            ->setDescription("Detailed order information for Order #$orderId")
            ->setKeywords("order, noblehome, export")
            ->setCategory("Order Management");
        
        // Header styling
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'EA580C'] // Orange color
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
        ];
        
        // Data styling
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];
        
        // Title
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'NOBLEHOME - ORDER DETAILS');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'EA580C']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
        $sheet->getRowDimension('1')->setRowHeight(30);
        
        // Order Information Header
        $sheet->mergeCells('A3:G3');
        $sheet->setCellValue('A3', 'ORDER INFORMATION');
        $sheet->getStyle('A3')->applyFromArray($headerStyle);
        
        // Order details
        $row = 4;
        $orderInfo = [
            ['Order ID:', '#' . $orderId],
            ['Status:', $order['status']],
            ['Date:', date('F j, Y - h:i A', strtotime($order['created_at']))],
            ['Customer Name:', $order['customer_name']],
            ['Email:', $order['email']],
            ['Mobile:', $order['mobile']],
            ['Address:', $order['address'] . ', ' . $order['zipcode']],
            ['Payment Mode:', $order['mode_payment'] ?? 'N/A']
        ];
        
        foreach ($orderInfo as $info) {
            $sheet->setCellValue('A' . $row, $info[0]);
            $sheet->setCellValue('B' . $row, $info[1]);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray($dataStyle);
            $row++;
        }
        
        // Order Items Header
        $row += 2;
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->setCellValue('A' . $row, 'ORDER ITEMS');
        $sheet->getStyle('A' . $row)->applyFromArray($headerStyle);
        
        // Items table headers
        $row++;
        $headers = ['Product Name', 'Size', 'Color', 'Quantity', 'Unit Price', 'Total Price'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F'];
        
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($columns[$index] . $row, $header);
        }
        $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($headerStyle);
        
        // Items data
        $row++;
        $subtotal = 0;
        
        if ($itemsResult && $itemsResult->num_rows > 0) {
            while ($item = $itemsResult->fetch_assoc()) {
                $itemTotal = floatval($item['price']) * intval($item['quantity']);
                $subtotal += $itemTotal;
                
                $sheet->setCellValue('A' . $row, $item['product_name']);
                $sheet->setCellValue('B' . $row, $item['size'] ?? 'N/A');
                $sheet->setCellValue('C' . $row, $item['variant_color'] ?? 'N/A');
                $sheet->setCellValue('D' . $row, intval($item['quantity']));
                $sheet->setCellValue('E' . $row, '₱' . number_format(floatval($item['price']), 2));
                $sheet->setCellValue('F' . $row, '₱' . number_format($itemTotal, 2));
                
                $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($dataStyle);
                $row++;
            }
        }
        
        // Order Summary
        $row += 2;
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->setCellValue('A' . $row, 'ORDER SUMMARY');
        $sheet->getStyle('A' . $row)->applyFromArray($headerStyle);
        
        $row++;
        $discount = floatval($order['discount']);
        $shipping = floatval($order['shipping_fee']);
        $discountAmount = ($subtotal * $discount) / 100;
        $finalTotal = ($subtotal - $discountAmount) + $shipping;
        
        $summary = [
            ['Subtotal:', '₱' . number_format($subtotal, 2)],
            ['Discount (' . $discount . '%):', '-₱' . number_format($discountAmount, 2)],
            ['Shipping Fee:', '₱' . number_format($shipping, 2)],
            ['TOTAL AMOUNT:', '₱' . number_format($finalTotal, 2)]
        ];
        
        foreach ($summary as $index => $sum) {
            $sheet->setCellValue('E' . $row, $sum[0]);
            $sheet->setCellValue('F' . $row, $sum[1]);
            
            if ($index === 3) { // Total row
                $sheet->getStyle('E' . $row . ':F' . $row)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'EA580C']
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_THICK,
                            'color' => ['rgb' => 'EA580C']
                        ]
                    ]
                ]);
            } else {
                $sheet->getStyle('E' . $row)->getFont()->setBold(true);
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Set minimum column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(10);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        
        // Footer
        $row += 3;
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->setCellValue('A' . $row, 'Generated on ' . date('F j, Y \a\t h:i A') . ' | NobleHome Admin Panel');
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 9,
                'color' => ['rgb' => '666666']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);
        
        // Create Excel file
        $writer = new Xlsx($spreadsheet);
        
        // Set headers for download
        $filename = 'NobleHome_Order_' . $orderId . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        
        $writer->save('php://output');
        exit;
    } else {
        echo "<script>alert('Order not found!'); window.close();</script>";
    }
} else {
    echo "<script>alert('No order ID provided!'); window.close();</script>";
}
?>