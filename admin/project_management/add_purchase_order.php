<?php
// add_purchase_order.php - Add Purchase Order with Quotation Builder
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Dompdf\Dompdf;
use Dompdf\Options;

session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_once '../../vendor/autoload.php';
require_role(['sales', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get user info from session or database
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_id'] = null;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

// Set user variables
$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];
$user_level = $_SESSION['noble_lvl'];

// Get company_id from URL
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

if ($company_id <= 0) {
    header("Location: view_companies.php");
    exit();
}

// Get company details
$stmt = $conn->prepare("SELECT company_name, company_address, logo_path FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stmt->bind_result($company_name, $company_address, $logo_path);
if (!$stmt->fetch()) {
    header("Location: view_companies.php");
    exit();
}
$stmt->close();

// Function to fetch logo from database
function getCompanyLogoBlob($conn, $company_id)
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
$logo_blob = getCompanyLogoBlob($conn, $company_id);

if ($logo_blob) {
    // Convert BLOB to base64 for PDF
    $base64_logo = base64_encode($logo_blob);
    $absolute_logo_path = 'data:image/png;base64,' . $base64_logo;
} else {
    // Fallback to default logo path
    $absolute_logo_path = __DIR__ . '/../../img/logo/logo.png';
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_po'])) {
    $po_number = trim($_POST['po_number']);
    $po_date = trim($_POST['po_date']);
    $ship_to = trim($_POST['ship_to']);
    $target_delivery = trim($_POST['target_delivery']);
    $payment_terms = trim($_POST['payment_terms']);
    $project_scope = trim($_POST['project_scope']);
    $cart_data = $_POST['cart_data'] ?? '[]';
    $client_po_path = null;

    // Validate required fields
    if (empty($po_number) || empty($po_date) || empty($ship_to) || empty($target_delivery) || empty($payment_terms) || empty($project_scope)) {
        $error = "All PO details are required!";
    } elseif ($cart_data === '[]' || empty($cart_data)) {
        $error = "Cart cannot be empty! Add products to quotation.";
    } else {
        // Create XLS file from cart data
        $cartItems = json_decode($cart_data, true);

        if (empty($cartItems)) {
            $error = "Invalid cart data!";
        } else {
            // Create uploads directory if needed
            $upload_dir = __DIR__ . '/../../uploads/purchase_orders/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate PDF filename
            $new_filename = 'po_' . $company_id . '_' . time() . '.pdf';
            $full_path = $upload_dir . $new_filename;
            $attachment_path = '../../uploads/purchase_orders/' . $new_filename;

// Create PDF content
$pdfContent = createQuotationPDF($company_name, $po_number, $po_date, $cartItems, $fullname, $company_address, $absolute_logo_path, $ship_to, $target_delivery, $payment_terms, $project_scope);

            // Handle client's original PO upload
            if (isset($_FILES['client_po']) && $_FILES['client_po']['error'] === UPLOAD_ERR_OK) {
                $client_upload_dir = __DIR__ . '/../../uploads/client_pos/';
                if (!file_exists($client_upload_dir)) {
                    mkdir($client_upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['client_po']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $client_po_filename = 'client_po_' . $company_id . '_' . time() . '.' . $file_extension;
                    $client_full_path = $client_upload_dir . $client_po_filename;
                    $client_po_path = '../../uploads/client_pos/' . $client_po_filename;
                    
                    if (!move_uploaded_file($_FILES['client_po']['tmp_name'], $client_full_path)) {
                        $error = "Failed to upload client's PO.";
                        $client_po_path = null;
                    }
                }
            }

            if (file_put_contents($full_path, $pdfContent)) {
                // Insert into database
$status = 'pending';
$stmt = $conn->prepare("INSERT INTO purchase_orders (company_id, sales_user_id, po_number, po_date, ship_to, target_delivery_date, payment_terms, project_scope, attachment_path, client_po_path, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("iisssssssss", $company_id, $user_id, $po_number, $po_date, $ship_to, $target_delivery, $payment_terms, $project_scope, $attachment_path, $client_po_path, $status);

                if ($stmt->execute()) {
    $po_id = $conn->insert_id; // Get the ID of the newly created PO
    
    // Insert cart items into purchase_order_items table
    $item_stmt = $conn->prepare("INSERT INTO purchase_order_items 
        (po_id, product_id, product_color_id, product_variant_id, product_name, color_name, size, quantity, unit_price, subtotal, is_custom_size) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $items_saved = true;
    foreach ($cartItems as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $variant_id = isset($item['variantId']) && $item['variantId'] !== null ? intval($item['variantId']) : null;
        $is_custom = isset($item['isCustomSize']) ? ($item['isCustomSize'] ? 1 : 0) : 0;
        
        $product_id = intval($item['id']);
        $color_id = intval($item['colorId']);
        $product_name = $item['name'];
        $color_name = $item['colorName'];
        $size = $item['size'];
        $quantity = intval($item['quantity']);
        $unit_price = floatval($item['price']);
        
        // bind_param: 11 parameters total
        // i = integer, s = string, d = double/float
        $item_stmt->bind_param("iiiisssiddi", 
            $po_id,           // i - integer
            $product_id,      // i - integer
            $color_id,        // i - integer
            $variant_id,      // i - integer (can be NULL)
            $product_name,    // s - string
            $color_name,      // s - string
            $size,            // s - string
            $quantity,        // i - integer
            $unit_price,      // d - double
            $subtotal,        // d - double
            $is_custom        // i - integer (0 or 1)
        );
        
        if (!$item_stmt->execute()) {
            $items_saved = false;
            error_log("Failed to save item: " . $item_stmt->error);
            break;
        }
    }
    
    $item_stmt->close();
    
    if ($items_saved) {
        $_SESSION['po_success'] = "Purchase Order created successfully!";
        header("Location: purchase_orders.php?company_id=" . $company_id);
        exit();
    } else {
        $error = "Failed to save purchase order items. Please try again.";
        @unlink($full_path);
        // Optionally delete the PO record too
        $conn->query("DELETE FROM purchase_orders WHERE id = $po_id");
    }
} else {
    $error = "Failed to save purchase order. Please try again.";
    @unlink($full_path);
}
$stmt->close();
            } else {
                $error = "Failed to generate quotation PDF.";
            }
        }
    }
}

// Function to create professional PDF file with logo
function createQuotationPDF($company_name, $po_number, $po_date, $cartItems, $username, $company_address = '', $logo_path = '', $ship_to = '', $target_delivery = '', $payment_terms = '', $project_scope = '')
{
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isFontSubsettingEnabled', true);
    $dompdf = new Dompdf($options);

    // Use logo path directly (supports both file paths and data URIs)
    $logoImg = '<img src="' . $logo_path . '" style="height: 60px; width: auto; background: white; padding: 5px; border-radius: 4px;">';

    // Extract first line of address if available
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

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8px;
            color: #666;
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
                        <p>Construction </p>
                        <p>' . htmlspecialchars($company_name) . '</p>
                    </div>
                </div>
            </div>
            <div class="header-right">
                <div class="po-badge">PURCHASE<br>ORDER</div>
            </div>
        </div>
    </div>

    <!-- Project Scope Section (Above Info Boxes) -->
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

    // Calculate VAT (12%) and General Requirements (10%) from subtotal
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

    <!-- Footer -->
    <div class="footer">
        <p>This is an electronically generated Purchase Order. Valid only with authorized signature and company seal.</p>
        <p>For inquiries, please contact us.</p>
    </div>
</body>
</html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');

    // Disable remote content for safety
    $options->set('isRemoteEnabled', false);

    try {
        $dompdf->render();
        return $dompdf->output();
    } catch (Exception $e) {
        error_log("DOMPDF Error: " . $e->getMessage());
        throw $e;
    }
}

// Fetch all products with colors and variants
mysqli_query($conn, "SET SESSION group_concat_max_len = 10000;");

$products_data = [];
$material_query = "
    SELECT 
        p.id AS product_id,
        p.product_name,
        pc.id AS color_id,
        pc.color_name,
        pc.color_code,
        pc.price AS color_price,
        pc.image AS color_image,
        GROUP_CONCAT(
            DISTINCT
            JSON_OBJECT(
                'variant_id', pv.id,
                'size', pv.size,
                'price', pv.price,
                'percent', pv.percent,
                'discount', pv.discount,
                'origin', pv.origin
            )
            ORDER BY pv.size
        ) AS variants
    FROM products p
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    WHERE pc.id IS NOT NULL
    GROUP BY p.id, pc.id
    ORDER BY p.id DESC, pc.id ASC
    LIMIT 50
";

$material_results = mysqli_query($conn, $material_query);
if ($material_results && mysqli_num_rows($material_results) > 0) {
    while ($row = mysqli_fetch_assoc($material_results)) {
        $variants = [];
        if (!empty($row['variants'])) {
            $variants = json_decode('[' . $row['variants'] . ']', true);
        }

        $first_variant = $variants[0] ?? ['variant_id' => 0, 'size' => 'One Size', 'price' => 0];
        $base_price = (float)$first_variant['price'] + (float)$row['color_price'];

        $color_image_path = !empty($row['color_image']) ? '../../' . $row['color_image'] : '../img/placeholder.jpg';

        $products_data[] = [
            'id' => (int)$row['product_id'],
            'color_id' => (int)$row['color_id'],
            'name' => htmlspecialchars($row['product_name']),
            'color_name' => htmlspecialchars($row['color_name']),
            'image' => htmlspecialchars($color_image_path),
            'price' => $base_price,
            'variants' => $variants,
            'first_variant' => $first_variant,
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Purchase Order - <?php echo htmlspecialchars($company_name); ?></title>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen font-roboto">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
            <div class="py-3 sm:py-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <a href="purchase_orders.php?company_id=<?php echo $company_id; ?>" class="bg-gray-200 hover:bg-gray-300 p-2 rounded-lg transition">
                            <i class="fas fa-arrow-left text-gray-700"></i>
                        </a>

                        <?php if ($logo_blob): ?>
                            <img src="data:image/png;base64,<?php echo base64_encode($logo_blob); ?>"
                                alt="<?php echo htmlspecialchars($company_name); ?>"
                                class="h-12 w-12 object-contain rounded-lg border border-gray-200">
                        <?php else: ?>
                            <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-building text-blue-600 text-xl"></i>
                            </div>
                        <?php endif; ?>

                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">
                                Create Purchase Order
                            </h1>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($company_name); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8 py-4 sm:py-8">

        <?php if ($error): ?>
            <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 mb-6 flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 text-2xl mr-3"></i>
                <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="poForm" enctype="multipart/form-data" class="space-y-6">

            <!-- PO Details Section -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 sm:px-6 py-3 sm:py-4">
                    <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-file-invoice mr-2 sm:mr-3"></i>
                        Purchase Order Details
                    </h2>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- PO Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-hashtag mr-1 text-green-600"></i>Purchase Order # *
                            </label>
                            <input type="text" name="po_number" required
                                value="<?php echo isset($_POST['po_number']) ? htmlspecialchars($_POST['po_number']) : ''; ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="e.g., PO-2024-001">
                        </div>

                        <!-- PO Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-1 text-green-600"></i>Purchase Order Date *
                            </label>
                            <input type="date" name="po_date" required
                                value="<?php echo isset($_POST['po_date']) ? htmlspecialchars($_POST['po_date']) : date('Y-m-d'); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Ship To -->
<div class="mt-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fas fa-shipping-fast mr-1 text-green-600"></i>Ship To *
    </label>
    <textarea name="ship_to" required rows="3"
        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
        placeholder="Enter complete shipping address"><?php echo isset($_POST['ship_to']) ? htmlspecialchars($_POST['ship_to']) : ''; ?></textarea>
</div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <!-- Target Delivery Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-truck mr-1 text-green-600"></i>Target Delivery Date *
                            </label>
                            <input type="date" name="target_delivery" required
                                value="<?php echo isset($_POST['target_delivery']) ? htmlspecialchars($_POST['target_delivery']) : ''; ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>

                        <!-- Payment Terms -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>Payment Terms *
    </label>
    <input type="text" name="payment_terms" required
        value="<?php echo isset($_POST['payment_terms']) ? htmlspecialchars($_POST['payment_terms']) : ''; ?>"
        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
        placeholder="e.g., Net 30, COD, 50% deposit">
</div>
                    </div>

                    <!-- Project Scope -->
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-clipboard-list mr-1 text-green-600"></i>Project Scope *
                        </label>
                        <textarea name="project_scope" required rows="3"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Describe the project scope and deliverables"><?php echo isset($_POST['project_scope']) ? htmlspecialchars($_POST['project_scope']) : ''; ?></textarea>
                    </div>

                    <!-- Client's Original PO Upload -->
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-file-upload mr-1 text-green-600"></i>Client's Original PO (Optional)
                        </label>
                        <input type="file" name="client_po" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Upload the client's original purchase order document (PDF, Image, or Word)</p>
                    </div>
                </div>
            </div>

            <!-- Quotation Builder Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

                <!-- Products Section (1 column) - Enhanced UI -->
                <div class="lg:col-span-1 bg-white  overflow-hidden flex flex-col h-full">
                    <!-- Header with gradient -->
                    <div class="bg-black px-6 py-5">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-boxes mr-3 text-orange-100"></i>Products
            </h2>
            <p class="text-sm text-orange-50 mt-2 font-medium"><span id="productCount"><?php echo count($products_data); ?></span> items available</p>
        </div>
        <div class="bg-white bg-opacity-20 rounded-full p-3 backdrop-blur-sm">
            <i class="fas fa-shopping-bag text-white text-lg"></i>
        </div>
    </div>
    
    <!-- Search Bars -->
    <div class="space-y-2">
        <div class="relative">
            <input type="text" id="searchProduct" placeholder="Search by product name..." 
                class="w-full px-4 py-2 pl-10 rounded-lg border-2 border-white border-opacity-20 bg-white bg-opacity-10 text-white placeholder-orange-100 focus:outline-none focus:border-orange-300 text-sm">
            <i class="fas fa-search absolute left-3 top-3 text-orange-100"></i>
        </div>
        <div class="relative">
            <input type="text" id="searchColor" placeholder="Search by color..." 
                class="w-full px-4 py-2 pl-10 rounded-lg border-2 border-white border-opacity-20 bg-white bg-opacity-10 text-white placeholder-orange-100 focus:outline-none focus:border-orange-300 text-sm">
            <i class="fas fa-palette absolute left-3 top-3 text-orange-100"></i>
        </div>
    </div>
</div>

                    <!-- Products List -->
                    <div class="p-4 overflow-y-auto flex-1" style="max-height: calc(100vh - 250px);">
                        <div id="productsList" class="grid grid-cols-1 gap-3">
                            <?php foreach ($products_data as $product): ?>
                                <div class="group relative  p-3 hover:from-orange-50 hover:to-orange-100 transition-all duration-300 cursor-pointer shadow-sm hover:shadow-lg hover:scale-102 border border-transparent hover:border-orange-200"
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-color-id="<?php echo $product['color_id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    data-color-name="<?php echo htmlspecialchars($product['color_name']); ?>"
                                    data-price="<?php echo $product['price']; ?>"
                                    data-variants='<?php echo json_encode($product['variants']); ?>'
                                    data-color-price="<?php echo $product['price'] - $product['first_variant']['price']; ?>">

                                    <!-- Product Image Container -->
                                    <div class="flex gap-3 mb-3">
                                        <div class="relative h-16 w-16 flex-shrink-0 bg-white rounded-lg overflow-hidden shadow-sm group-hover:shadow-md transition-shadow">
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>"
                                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                class="h-full w-full object-contain p-1">
                                            <div class="absolute inset-0 bg-gradient-to-tr from-orange-400 to-transparent opacity-0 group-hover:opacity-10 transition-opacity"></div>
                                        </div>

                                        <!-- Product Info -->
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-sm text-gray-800 line-clamp-2 group-hover:text-orange-700 transition-colors">
                                                <?php echo htmlspecialchars(substr($product['name'], 0, 40)); ?>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <?php echo htmlspecialchars(substr($product['color_name'], 0, 40)); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Price Section -->
                                    <div class="flex items-end justify-between mb-3 pb-3 border-b border-gray-200 group-hover:border-orange-200 transition-colors">
                                        <div class="text-right">
                                            <span class="inline-block bg-orange-100 text-orange-700 px-2 py-1 rounded-full text-xs font-semibold">
                                                <?php echo count($product['variants']); ?> variant<?php echo count($product['variants']) > 1 ? 's' : ''; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Add to Cart Button -->
                                    <button type="button" class="w-full add-to-cart-btn bg-black hover:from-orange-600 hover:to-red-600 text-white text-sm font-bold py-2.5 px-3 rounded-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0">
                                        <i class="fas fa-plus text-xs"></i>
                                        <span>Add to Cart</span>
                                    </button>

                                    <!-- Hover Indicator -->
                                    <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i class="fas fa-arrow-right text-orange-400 text-xs"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Empty State (optional) -->
                        <?php if (empty($products_data)): ?>
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="fas fa-inbox text-gray-300 text-5xl mb-3"></i>
                                <p class="text-gray-500 font-medium">No products available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cart Section (2 columns) -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden flex flex-col">
                    <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
                        <h2 class="text-lg font-bold text-white flex items-center">
                            <i class="fas fa-shopping-cart mr-2"></i>Quotation Items
                        </h2>
                        <p class="text-xs text-green-100 mt-1">Items: <span id="cartItemCount" class="font-bold">0</span></p>
                    </div>

                    <div id="cartDropZone" class="p-4 min-h-96 bg-gray-50 border-t-2 border-dashed border-gray-300 flex-1 overflow-x-auto">
                        <p class="text-gray-500 text-center py-16">
                            <i class="fas fa-arrow-left text-3xl mb-2 block text-gray-300"></i>
                            Click Add to populate items
                        </p>
                    </div>

                    <!-- Cart Summary -->
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal:</span>
                                <span id="cartSubtotal" class="text-gray-900 font-medium">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">VAT (12%):</span>
                                <span id="cartVat" class="text-gray-900 font-medium">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-sm pb-2 border-b border-gray-200">
                                <span class="text-gray-600">General Requirements (10%):</span>
                                <span id="cartGeneralReq" class="text-gray-900 font-medium">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-base font-bold pt-2">
                                <span class="text-gray-700">Total:</span>
                                <span id="cartTotal" class="text-green-600">₱0.00</span>
                            </div>
                        </div>

                        <button type="button" id="clearCartBtn" class="w-full bg-red-300 hover:bg-red-400 text-red-900 font-bold py-2 rounded-lg transition-all text-sm">
                            <i class="fas fa-trash mr-2"></i>Clear All Items
                        </button>
                    </div>
                </div>
            </div>

            <!-- Hidden Cart Data Input -->
            <input type="hidden" name="cart_data" id="cartData" value="[]">

            <!-- Submit Buttons -->
            <div class="flex gap-3">
                <a href="purchase_orders.php?company_id=<?php echo $company_id; ?>"
                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg transition-colors duration-200 font-medium text-center">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" name="submit_po"
                    class="flex-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center justify-center space-x-2 font-medium">
                    <i class="fas fa-check"></i>
                    <span>Create Purchase Order</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Size Selection Modal - Clean & Organized UI -->
    <div id="sizeModal" class="hidden fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fadeIn">
        <div class="bg-white max-w-md w-full overflow-hidden transform transition-all duration-300 animate-slideUp">

            <!-- Modal Header -->
            <div class="bg-black px-6 py-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-ruler mr-2"></i>Select Size
                        </h3>
                        <p class="text-orange-50 text-sm mt-1">Choose your preferred size</p>
                    </div>
                    <button type="button" onclick="closeSizeModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full w-9 h-9 flex items-center justify-center transition-all duration-200 flex-shrink-0">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <!-- Availability Alert -->
                <div class=" p-4 mb-6 flex items-start gap-3">
                    <i class="fas fa-check-circle text-green-600 mt-1 flex-shrink-0"></i>
                    <p class="text-sm text-green-800 font-medium">All sizes are available for this item</p>
                </div>
        
                <!-- Size Options -->
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-800 mb-4">Available Sizes:</label>
                    <div id="sizeOptions" class="grid grid-cols-2 gap-3">
                        <!-- Sizes will be populated here -->
                    </div>
                </div>

                <!-- Custom Size Option -->
                <div class="border-t border-gray-200 pt-6">
                    <div class="flex items-center mb-4">
                        <input type="checkbox" id="customSizeToggle" class="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                        <label for="customSizeToggle" class="ml-2 text-sm font-bold text-gray-800">Use Custom Size</label>
                    </div>
                    
                    <div id="customSizeInputs" class="hidden space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Custom Size Name *</label>
                            <input type="text" id="customSizeName" placeholder="e.g., 2.0*2.5 or Custom XL"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Custom Price (₱) *</label>
                            <input type="number" id="customSizePrice" placeholder="0.00" step="0.01" min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent text-sm">
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p class="text-xs text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Note:</strong> Custom size price will be added to the color price (₱<span id="colorPriceDisplay">0.00</span>)
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 border-t border-gray-200 px-6 py-4 flex gap-3">
                <button type="button" onclick="closeSizeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-4 rounded-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                    <i class="fas fa-arrow-left"></i>
                    <span>Cancel</span>
                </button>
                <button type="button" onclick="confirmSize()" class="flex-1 bg-black hover:from-orange-600 hover:to-red-600 text-white font-bold py-3 px-4 rounded-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0">
                    <span>Add to Cart</span>
                </button>
            </div>
        </div>
    </div>

    <!-- CSS Animations & Styles -->
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .animate-fadeIn {
            animation: fadeIn 0.25s ease-out;
        }

        .animate-slideUp {
            animation: slideUp 0.35s ease-out;
        }

        /* Size option button styles */
        .size-option-btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .size-option-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(249, 115, 22, 0.15);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .size-option-btn:hover {
            border-color: #f97316;
            background-color: #fffbeb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
        }

        .size-option-btn.active {
        
            border-color: #ea580c;
            color: white;
            font-weight: bold;
            box-shadow: 0 6px 20px rgba(249, 115, 22, 0.4);
            transform: scale(1.05);
        }

        .size-option-btn.active::before {
            width: 400px;
            height: 400px;
        }
    </style>

    <script>
        let cartItems = [];
        let selectedProduct = null;
        let selectedSize = null;

        // Open size selection modal
        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const productDiv = btn.closest('[data-product-id]');
                selectedProduct = {
                    id: productDiv.dataset.productId,
                    colorId: productDiv.dataset.colorId,
                    name: productDiv.dataset.productName,
                    colorName: productDiv.dataset.colorName,
                    colorPrice: parseFloat(productDiv.dataset.colorPrice),
                    variants: JSON.parse(productDiv.dataset.variants || '[]')
                };

                showSizeModal();
            });
        });

        // Custom size toggle functionality
        document.getElementById('customSizeToggle').addEventListener('change', function() {
            const customInputs = document.getElementById('customSizeInputs');
            const sizeButtons = document.querySelectorAll('.size-option-btn');
            
            if (this.checked) {
                customInputs.classList.remove('hidden');
                // Deselect all size buttons
                sizeButtons.forEach(btn => {
                    btn.classList.remove('active', 'border-orange-500', 'text-white');
                });
                selectedSize = null;
            } else {
                customInputs.classList.add('hidden');
                document.getElementById('customSizeName').value = '';
                document.getElementById('customSizePrice').value = '';
            }
        });

   // Update the showSizeModal() function to include prices

function showSizeModal() {
    if (!selectedProduct || !selectedProduct.variants.length) {
        addToCartDirect();
        return;
    }

    const sizeOptions = document.getElementById('sizeOptions');
    sizeOptions.innerHTML = '';
    selectedSize = null;
    
    // Reset custom size inputs
    document.getElementById('customSizeToggle').checked = false;
    document.getElementById('customSizeInputs').classList.add('hidden');
    document.getElementById('customSizeName').value = '';
    document.getElementById('customSizePrice').value = '';
    
    // Display color price for custom size reference
    document.getElementById('colorPriceDisplay').textContent = selectedProduct.colorPrice.toLocaleString('en-US', {minimumFractionDigits: 2});

    selectedProduct.variants.forEach((variant, idx) => {
        const totalPrice = parseFloat(variant.price) + parseFloat(selectedProduct.colorPrice);
        
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'size-option-btn bg-white border-2 border-gray-300 rounded-lg py-3 px-2 transition-all duration-200 cursor-pointer text-center hover:border-orange-400 hover:bg-orange-50';
        btn.innerHTML = `
            <div class="font-semibold text-sm text-gray-800 mb-1">${variant.size}</div>
            <div class="text-orange-600 font-bold text-sm">₱${totalPrice.toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
        `;
        btn.dataset.variantIdx = idx;
        
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            // Uncheck custom size toggle
            document.getElementById('customSizeToggle').checked = false;
            document.getElementById('customSizeInputs').classList.add('hidden');
            
            document.querySelectorAll('.size-option-btn').forEach(b => {
                b.classList.remove('active', 'border-orange-500', 'text-white');
            });
            btn.classList.add('active', 'border-orange-500','text-white');
            selectedSize = idx;
        });
        
        sizeOptions.appendChild(btn);
    });

    document.getElementById('sizeModal').classList.remove('hidden');
}

function closeSizeModal() {
    document.getElementById('sizeModal').classList.add('hidden');
    selectedProduct = null;
    selectedSize = null;
}

function confirmSize() {
    const isCustomSize = document.getElementById('customSizeToggle').checked;
    
    if (isCustomSize) {
        const customName = document.getElementById('customSizeName').value.trim();
        const customPrice = parseFloat(document.getElementById('customSizePrice').value);
        
        if (!customName) {
            alert('Please enter a custom size name');
            return;
        }
        
        if (isNaN(customPrice) || customPrice < 0) {
            alert('Please enter a valid price');
            return;
        }
        
        addToCartWithCustomSize(customName, customPrice);
    } else {
        if (selectedSize === null && selectedProduct.variants.length > 0) {
            alert('Please select a size or use custom size');
            return;
        }
        addToCartDirect();
    }
    
    closeSizeModal();
}

function addToCartWithCustomSize(customSizeName, customSizePrice) {
    if (!selectedProduct) return;

    const totalPrice = parseFloat(customSizePrice) + parseFloat(selectedProduct.colorPrice);

    const product = {
        id: selectedProduct.id,
        colorId: selectedProduct.colorId,
        variantId: null, // Custom size has no variant ID
        name: selectedProduct.name,
        colorName: selectedProduct.colorName,
        size: customSizeName,
        price: totalPrice,
        quantity: 1,
        isCustomSize: true
    };

    const key = `${product.id}-${product.colorId}-${customSizeName}`;
    const existingItem = cartItems.find(item => item.key === key);

    if (existingItem) {
        existingItem.quantity++;
    } else {
        cartItems.push({
            key: key,
            ...product
        });
    }

    updateCart();
}

function addToCartDirect() {
    if (!selectedProduct) return;

    const variantIdx = selectedSize !== null ? selectedSize : 0;
    const variant = selectedProduct.variants[variantIdx] || {
        variant_id: null,
        size: 'One Size',
        price: 0
    };
    const totalPrice = parseFloat(variant.price) + parseFloat(selectedProduct.colorPrice);

    const product = {
        id: selectedProduct.id,
        colorId: selectedProduct.colorId,
        variantId: variant.variant_id || null,
        name: selectedProduct.name,
        colorName: selectedProduct.colorName,
        size: variant.size,
        price: totalPrice,
        quantity: 1,
        isCustomSize: false
    };

    const key = `${product.id}-${product.colorId}-${product.size}`;
    const existingItem = cartItems.find(item => item.key === key);

    if (existingItem) {
        existingItem.quantity++;
    } else {
        cartItems.push({
            key: key,
            ...product
        });
    }

    updateCart();
}
        const cartDropZone = document.getElementById('cartDropZone');

        function updateCart() {
            cartDropZone.innerHTML = '';

            if (cartItems.length === 0) {
                cartDropZone.innerHTML = '<p class="text-gray-500 text-center py-16"><i class="fas fa-arrow-left text-3xl mb-2 block text-gray-300"></i>Click Add to populate items</p>';
            } else {
                const table = document.createElement('table');
                table.className = 'w-full text-sm';
                table.innerHTML = `
    <thead class="bg-gray-200 border-b-2 sticky top-0">
        <tr>
            <th class="text-left p-2 font-semibold text-xs">Product</th>
            <th class="text-left p-2 font-semibold text-xs">Color</th>
            <th class="text-left p-2 font-semibold text-xs">Size</th>
            <th class="text-center p-2 font-semibold text-xs">Qty</th>
            <th class="text-right p-2 font-semibold text-xs">Unit Price</th>
            <th class="text-right p-2 font-semibold text-xs">Subtotal</th>
            <th class="text-right p-2 font-semibold text-xs">Action</th>
        </tr>
    </thead>
                    <tbody>
    ${cartItems.map((item, idx) => `
        <tr class="border-b hover:bg-orange-50 text-xs">
            <td class="p-2 font-medium text-gray-900">${item.name.substring(0, 15)}</td>
            <td class="p-2 text-gray-700">${item.colorName.substring(0, 12)}</td>
            <td class="p-2 text-gray-700 font-semibold">${item.size}</td>
            <td class="text-center p-2">
                <input type="number" min="1" value="${item.quantity}" 
                       onchange="updateQuantity(${idx}, this.value)"
                       class="w-16 border rounded px-1 py-1 text-center text-xs">
            </td>
            <td class="text-right p-2">
                <input type="number" min="0" step="0.01" value="${item.price.toFixed(2)}" 
                       onchange="updatePrice(${idx}, this.value)"
                       class="w-24 border rounded px-2 py-1 text-right text-xs font-bold text-green-600">
            </td>
            <td class="text-right p-2 font-bold text-green-600">₱${(item.price * item.quantity).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
            <td class="text-right p-2">
                <button type="button" onclick="removeFromCart(${idx})" class="text-red-600 hover:text-red-800 font-bold text-lg">
                    <i class="fas fa-trash text-xs"></i>
                </button>
            </td>
        </tr>
    `).join('')}
</tbody>
                `;
                cartDropZone.appendChild(table);
            }

            const subtotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const vat = subtotal * 0.12;
            const generalReq = subtotal * 0.10;
            const total = subtotal + vat + generalReq;
            
            document.getElementById('cartItemCount').textContent = cartItems.length;
            document.getElementById('cartSubtotal').textContent = `₱${subtotal.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('cartVat').textContent = `₱${vat.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('cartGeneralReq').textContent = `₱${generalReq.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('cartTotal').textContent = `₱${total.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('cartData').value = JSON.stringify(cartItems);
        }

        function updateQuantity(idx, value) {
            cartItems[idx].quantity = parseInt(value) || 1;
            updateCart();
        }

        function updatePrice(idx, value) {
    const newPrice = parseFloat(value);
    if (!isNaN(newPrice) && newPrice >= 0) {
        cartItems[idx].price = newPrice;
        updateCart();
    }
}

        function removeFromCart(idx) {
            cartItems.splice(idx, 1);
            updateCart();
        }

        document.getElementById('clearCartBtn').addEventListener('click', () => {
            if (confirm('Clear the cart?')) {
                cartItems = [];
                updateCart();
            }
        });

        document.getElementById('poForm').addEventListener('submit', (e) => {
            if (cartItems.length === 0) {
                e.preventDefault();
                alert('Please add products to the quotation!');
            }
        });

        // Search functionality for products
document.getElementById('searchProduct').addEventListener('input', function() {
    filterProducts();
});

document.getElementById('searchColor').addEventListener('input', function() {
    filterProducts();
});

function filterProducts() {
    const searchProduct = document.getElementById('searchProduct').value.toLowerCase();
    const searchColor = document.getElementById('searchColor').value.toLowerCase();
    const productCards = document.querySelectorAll('[data-product-id]');
    let visibleCount = 0;
    
    productCards.forEach(card => {
        const productName = card.dataset.productName.toLowerCase();
        const colorName = card.dataset.colorName.toLowerCase();
        
        const matchesProduct = productName.includes(searchProduct);
        const matchesColor = colorName.includes(searchColor);
        
        if (matchesProduct && matchesColor) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update count
    document.getElementById('productCount').textContent = visibleCount;
}
    </script>
</body>

</html>