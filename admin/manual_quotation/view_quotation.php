<?php
// view_quotation.php
session_name("nobleadmin");
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);





// Simple authentication check (you can integrate with your existing auth system)
if (!isset($_SESSION['noble_user'])) {
    header("Location: login.php");
    exit();
}

// Get quotation ID from URL
$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($quotation_id <= 0) {
    echo "<script>alert('Invalid quotation ID'); window.location.href='quotations_list.php';</script>";
    exit();
}

// Fetch quotation details
$stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ?");
$stmt->bind_param("i", $quotation_id);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quotation) {
    echo "<script>alert('Quotation not found'); window.location.href='quotations_list.php';</script>";
    exit();
}

// Fetch quotation items
$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id");
$stmt->bind_param("i", $quotation_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Helper functions
function formatCurrency($amount) {
    return '₱' . number_format((float)$amount, 2);
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function getStatusColor($status) {
    $colors = [
        'draft' => 'bg-yellow-100 text-yellow-800',
        'sent' => 'bg-blue-100 text-blue-800', 
        'approved' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formal Quotation - <?php echo htmlspecialchars($quotation['quotation_no']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            line-height: 1.4;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1e88e5;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .print-button:hover {
            background: #1565c0;
        }
        
        .quotation-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        /* Header Section */
        .header {
            background: linear-gradient(135deg, #1e88e5, #42a5f5);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: #1e88e5;
            flex-shrink: 0;
        }
        
        .company-details h1 {
            font-size: 22px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .company-details p {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 2px;
        }
        
        .quotation-info {
            text-align: right;
            min-width: 250px;
        }
        
        .quotation-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .quotation-number {
            background: rgba(255,255,255,0.2);
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .quotation-number p:first-child {
            font-size: 10px;
            opacity: 0.8;
            margin-bottom: 3px;
        }
        
        .quotation-number p:last-child {
            font-size: 18px;
            font-weight: bold;
        }
        
        .quotation-for-box {
            background: rgba(255,255,255,0.15);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .quotation-for-box p:first-child {
            font-size: 10px;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .quotation-for-box p:last-child {
            font-size: 13px;
            font-weight: bold;
            line-height: 1.3;
        }
        
        .date-info {
            font-size: 11px;
        }
        
        .date-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            gap: 15px;
        }
        
        .date-row span:last-child {
            font-weight: bold;
        }
        
        /* Status Badge */
        .status-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        /* Customer Section */
        .customer-section {
            background: #e3f2fd;
            padding: 20px 30px;
            border-left: 4px solid #1e88e5;
        }
        
        .customer-section h3 {
            color: #1e88e5;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
        }
        
        .customer-details {
            font-size: 13px;
            line-height: 1.5;
            color: #333;
        }
        
        .customer-details strong {
            color: #1e88e5;
        }
        
        /* Items Table */
        .items-section {
            padding: 30px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .items-table th {
            background: #1e88e5;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #1e88e5;
            font-size: 10px;
            text-transform: uppercase;
        }
        
        .items-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            text-align: center;
            vertical-align: middle;
        }
        
        .items-table td:first-child {
            text-align: left;
        }
        
        .items-table td:last-child {
            text-align: right;
            font-weight: bold;
            color: #1e88e5;
        }
        
        .items-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .items-table tbody tr:hover {
            background: #e3f2fd;
        }
        
        .item-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 2px;
        }
        
        .item-details {
            font-size: 10px;
            color: #666;
        }
        
        /* Grand Total */
        .grand-total {
            display: flex;
            justify-content: flex-end;
            margin: 20px 0;
            padding: 0 30px;
        }
        
        .total-box {
            background: linear-gradient(135deg, #1e88e5, #42a5f5);
            color: white;
            padding: 20px 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(30, 136, 229, 0.3);
        }
        
        .total-label {
            font-size: 12px;
            margin-bottom: 8px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .total-amount {
            font-size: 28px;
            font-weight: bold;
        }
        
        /* Bank Details */
        .bank-details {
            background: #f8f9fa;
            padding: 25px 30px;
            border-top: 3px solid #1e88e5;
        }
        
        .bank-details h4 {
            color: #1e88e5;
            font-size: 14px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
        }
        
        .bank-info {
            font-size: 12px;
            line-height: 1.6;
        }
        
        .bank-row {
            display: flex;
            margin-bottom: 8px;
            align-items: center;
        }
        
        .bank-label {
            font-weight: bold;
            width: 140px;
            color: #333;
        }
        
        .bank-value {
            color: #1e88e5;
            font-weight: 600;
        }
        
        /* Footer */
        .footer {
            padding: 20px 30px;
            text-align: center;
            color: #666;
            font-size: 11px;
            border-top: 1px solid #eee;
        }
        
        .generated-info {
            margin-top: 10px;
            font-style: italic;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .print-button {
                display: none;
            }
            
            .quotation-container {
                box-shadow: none;
                max-width: none;
                border-radius: 0;
            }
            
            .header {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .items-table th {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .total-box {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .quotation-info {
                text-align: center;
                min-width: auto;
            }
            
            .items-table {
                font-size: 10px;
            }
            
            .items-table th,
            .items-table td {
                padding: 6px 4px;
            }
        }
        
        /* Loading State */
        .loading {
            display: none;
            text-align: center;
            padding: 50px;
            color: #666;
        }
        
        /* Error State */
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-left: 4px solid #c62828;
            margin: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">
        🖨️ Print Quotation
    </button>

    <div class="quotation-container">
        <!-- Status Badge -->
        <div class="status-badge <?php echo getStatusColor($quotation['status']); ?>">
            <?php echo strtoupper($quotation['status']); ?>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <div class="logo">NH</div>
                <div class="company-details">
                    <h1>Noblehome Construction Corporation</h1>
                    <p>MC Premier St. Quezon City, Metro</p>
                    <p>Manila, Philippines</p>
                    <p>(02) 8822-1658 / (63) 0922-235-4563</p>
                    <p>www.noblehomedepot.com</p>
                </div>
            </div>
            
            <div class="quotation-info">
                <div class="quotation-title">Formal Quotation</div>
                
                <div class="quotation-number">
                    <p>QUOTATION #</p>
                    <p><?php echo htmlspecialchars($quotation['quotation_no']); ?></p>
                </div>
                
                <div class="quotation-for-box">
                    <p>QUOTATION FOR</p>
                    <p><?php echo htmlspecialchars($quotation['quotation_for']); ?></p>
                </div>
                
                <div class="date-info">
                    <div class="date-row">
                        <span>Quotation Date:</span>
                        <span><?php echo formatDate($quotation['quotation_date']); ?></span>
                    </div>
                    <div class="date-row">
                        <span>Valid Until:</span>
                        <span><?php echo formatDate($quotation['valid_until']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Section -->
        <div class="customer-section">
            <h3>Quotation Details</h3>
            <div class="customer-details">
                <strong>Address:</strong> <?php echo htmlspecialchars($quotation['address']); ?><br>
                <strong>Contact Person:</strong> <?php echo htmlspecialchars($quotation['contact_person']); ?><br>
                <strong>Prepared By:</strong> <?php echo htmlspecialchars($quotation['prepared_by']); ?>
                <?php if (!empty($quotation['employee'])): ?>
                    <br><strong>Employee:</strong> <?php echo htmlspecialchars($quotation['employee']); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Item</th>
                        <th style="width: 12%;">Size</th>
                        <th style="width: 8%;">Unit</th>
                        <th style="width: 8%;">Quantity</th>
                        <th style="width: 13%;">Unit Material Price</th>
                        <th style="width: 13%;">Unit Labor</th>
                        <th style="width: 13%;">Unit Total</th>
                        <th style="width: 13%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #666; font-style: italic; padding: 30px;">
                                No items found for this quotation
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td style="text-align: left;">
                                <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <?php if (!empty($item['description'])): ?>
                                    <div class="item-details"><?php echo htmlspecialchars($item['description']); ?></div>
                                <?php endif; ?>
                                <?php if ($item['width_mm'] > 0 && $item['height_mm'] > 0): ?>
                                    <div class="item-details">
                                        <?php echo number_format($item['width_mm'], 0); ?>mm × <?php echo number_format($item['height_mm'], 0); ?>mm
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['size_display'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($item['unit']); ?></td>
                            <td><?php echo number_format($item['quantity'], 0); ?></td>
                            <td><?php echo formatCurrency($item['unit_total_material']); ?></td>
                            <td>
                                <?php if ($item['unit_labor'] > 0): ?>
                                    <?php echo formatCurrency($item['unit_labor']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatCurrency($item['unit_total']); ?></td>
                            <td style="font-weight: bold;"><?php echo formatCurrency($item['total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Grand Total -->
        <div class="grand-total">
            <div class="total-box">
                <div class="total-label">Grand Total</div>
                <div class="total-amount"><?php echo formatCurrency($quotation['grand_total']); ?></div>
            </div>
        </div>
        
        <!-- Bank Details -->
        <div class="bank-details">
            <h4>Bank Details</h4>
            <div class="bank-info">
                <div class="bank-row">
                    <span class="bank-label">Account Name:</span>
                    <span class="bank-value">Noblehome Construction Corp.</span>
                </div>
                <div class="bank-row">
                    <span class="bank-label">Account Number:</span>
                    <span class="bank-value">1323801657</span>
                </div>
                <div class="bank-row">
                    <span class="bank-label">Bank:</span>
                    <span class="bank-value">BDO</span>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Thank you for choosing Noblehome Construction Corporation</p>
            <div class="generated-info">
                Generated on <?php echo date('F j, Y g:i A'); ?> | 
                Quotation ID: <?php echo $quotation_id; ?> | 
                Created by: <?php echo htmlspecialchars($quotation['created_by']); ?>
            </div>
        </div>
    </div>

    <script>
        // Print functionality
        function printQuotation() {
            window.print();
        }
        
        // Add keyboard shortcut for printing
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printQuotation();
            }
        });
        
        // Add loading state for slow connections
        window.addEventListener('load', function() {
            document.body.style.opacity = '1';
        });
        
        // Add error handling for missing images
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                this.style.display = 'none';
            });
        });
        
        console.log('Quotation System loaded successfully');
        console.log('Quotation ID: <?php echo $quotation_id; ?>');
        console.log('Status: <?php echo $quotation['status']; ?>');
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>