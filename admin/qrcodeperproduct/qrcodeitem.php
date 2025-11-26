<?php
// admin/qrcodeperproduct/qrcodeitem.php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require '../../vendor/autoload.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

// Auto-regenerate QR codes on page load (only if needed)
$uploadsDir = __DIR__ . '/../../uploads/qrcodes/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

$regenerateCount = 0;
$fetchQuery = "SELECT id, codename FROM products";
$fetchResult = $conn->query($fetchQuery);

while ($row = $fetchResult->fetch_assoc()) {
    $productId = $row['id'];
    $qrFileName = "qr_product_" . $productId . ".png";
    $qrFilePath = $uploadsDir . $qrFileName;

    // Only regenerate if file doesn't exist
    if (!file_exists($qrFilePath)) {
        $qrText = "http://localhost/noble/admin/qrcodeperproduct/view_product.php?id=$productId";

        $resultQR = Builder::create()
            ->writer(new PngWriter())
            ->data($qrText)
            ->size(300)
            ->margin(10)
            ->build();

        $resultQR->saveToFile($qrFilePath);

        $stmt = $conn->prepare("UPDATE products SET qr_code = ? WHERE id = ?");
        $stmt->bind_param("si", $qrFileName, $productId);
        $stmt->execute();
        $stmt->close();

        $regenerateCount++;
    }
}

// Search & Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build display query with search & filter
$displayQuery = "
    SELECT 
        p.id as product_id, 
        p.product_name, 
        p.codename, 
        p.quantity, 
        p.price, 
        p.qr_code,
        p.created_at,
        COUNT(DISTINCT pv.id) as variant_count,
        GROUP_CONCAT(DISTINCT pv.namevariant SEPARATOR ', ') as variants_list
    FROM products p 
    LEFT JOIN product_variants pv ON p.id = pv.product_id 
    WHERE 1=1
";

// Add search condition
if (!empty($search)) {
    $searchParam = '%' . $search . '%';
    $displayQuery .= " AND (p.product_name LIKE '$searchParam' OR p.codename LIKE '$searchParam')";
}

// Add status filter
if ($filterStatus !== 'all') {
    if ($filterStatus === 'no-qr') {
        $displayQuery .= " AND p.qr_code IS NULL";
    } elseif ($filterStatus === 'has-qr') {
        $displayQuery .= " AND p.qr_code IS NOT NULL";
    } elseif ($filterStatus === 'no-variant') {
        $displayQuery .= " AND (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) = 0";
    }
}

$displayQuery .= " GROUP BY p.id ORDER BY p.product_name";

$displayResult = $conn->query($displayQuery);
$totalProducts = $displayResult->num_rows;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Products with QR Codes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2 flex items-center gap-3">
                <i class="fas fa-qrcode text-orange-600"></i>QR Code Management
            </h1>
            <p class="text-gray-600">Manage and distribute product QR codes efficiently</p>
        </div>

        <!-- Info Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Total Products</p>
                        <p class="text-3xl font-bold text-blue-600"><?= $totalProducts ?></p>
                    </div>
                    <i class="fas fa-box text-4xl text-blue-100"></i>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">QR Codes Generated</p>
                        <p class="text-3xl font-bold text-green-600"><?php
                            $qrCheck = $conn->query("SELECT COUNT(*) as count FROM products WHERE qr_code IS NOT NULL");
                            echo $qrCheck->fetch_assoc()['count'];
                        ?></p>
                    </div>
                    <i class="fas fa-check-circle text-4xl text-green-100"></i>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Missing QR Codes</p>
                        <p class="text-3xl font-bold text-orange-600"><?php
                            $missingCheck = $conn->query("SELECT COUNT(*) as count FROM products WHERE qr_code IS NULL");
                            echo $missingCheck->fetch_assoc()['count'];
                        ?></p>
                    </div>
                    <i class="fas fa-exclamation-circle text-4xl text-orange-100"></i>
                </div>
            </div>
        </div>

        <!-- Search & Filter Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" name="search" placeholder="Search by product name or codename..." 
                           value="<?= htmlspecialchars($search) ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400">
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Products</option>
                        <option value="has-qr" <?= $filterStatus === 'has-qr' ? 'selected' : '' ?>>Has QR Code</option>
                        <option value="no-qr" <?= $filterStatus === 'no-qr' ? 'selected' : '' ?>>Missing QR Code</option>
                        <option value="no-variant" <?= $filterStatus === 'no-variant' ? 'selected' : '' ?>>No Variant</option>
                    </select>
                </div>
                <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded-lg font-semibold transition flex items-center gap-2">
                    <i class="fas fa-search"></i>Search
                </button>
                <a href="?" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded-lg font-semibold transition">
                    Reset
                </a>
            </form>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <?php if ($displayResult && $totalProducts > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gradient-to-r from-gray-800 to-gray-700 text-white">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Product Name</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Codename</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold">Variants</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold">Quantity</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold">Price</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold">QR Code</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($product = $displayResult->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <p class="text-gray-900 font-medium"><?= htmlspecialchars($product['product_name']) ?></p>
                                        <p class="text-xs text-gray-500">ID: <?= $product['product_id'] ?></p>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($product['codename']) ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($product['variant_count'] > 0): ?>
                                            <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold">
                                                <?= $product['variant_count'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm italic">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center font-medium text-gray-900">
                                        <?= $product['quantity'] ?>
                                    </td>
                                    <td class="px-6 py-4 text-center font-semibold text-orange-600">
                                        ₱<?= number_format($product['price'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if (!empty($product['qr_code'])): ?>
                                            <div class="flex flex-col items-center gap-1">
                                                <a href="../../user/otherpage/index-view_product-page-16.php?id=<?= $product['product_id'] ?>" target="_blank" class="hover:opacity-75 transition">
                                                    <img src="../../uploads/qrcodes/<?= htmlspecialchars($product['qr_code']) ?>" 
                                                         class="h-12 w-12 border border-gray-300 rounded shadow-sm hover:shadow-md transition" 
                                                         alt="QR Code" title="Click to view product" />
                                                </a>
                                                <a href="../../uploads/qrcodes/<?= htmlspecialchars($product['qr_code']) ?>" 
                                                   download="qr_<?= $product['product_id'] ?>.png"
                                                   class="text-xs bg-green-500 hover:bg-green-600 text-white px-1.5 py-0.5 rounded transition flex items-center gap-1">
                                                    <i class="fas fa-download text-xs"></i>Download
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-red-500 font-medium text-sm">
                                                <i class="fas fa-times-circle mr-1"></i>Missing
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex gap-2 justify-center">
                                            <?php if ($product['variant_count'] > 0): ?>
                                                <a href="variant_edit.php?id=<?= $product['product_id'] ?>"
                                                   class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                                    <i class="fas fa-edit"></i>Variant
                                                </a>
                                            <?php endif; ?>
                                            <a href="product_images_edit.php?id=<?= $product['product_id'] ?>"
                                               class="bg-purple-500 hover:bg-purple-600 text-white text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                                <i class="fas fa-images"></i>Images
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center py-16 px-4">
                    <i class="fas fa-search text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg font-medium">No products found</p>
                    <p class="text-gray-400 text-sm mt-1">Try adjusting your search filters</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer Stats -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <p class="text-gray-600 text-sm text-center">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                Showing <?= $totalProducts ?> product(s) | 
                QR codes are automatically generated and stored in <?= $uploadsDir ?>
            </p>
        </div>

    </div>

</body>

</html>