<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require '../../vendor/autoload.php';
require_once '../role/roleaccount.php'; 

require_role(['productspecialist','superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
    header("Location: ../../loginpage/index.php");
    exit();
}

// Optional: Auto-logout after inactivity (e.g. 30 mins)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    // Destroy session and redirect to login
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}


use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

// ✅ Ensure the qrcodes directory exists
$qrDir = __DIR__ . '/qrcodes/';
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0777, true);
}

// ✅ Regenerate QR codes with PNG files
$fetchQuery = "SELECT id, codename FROM products";
$fetchResult = $conn->query($fetchQuery);

while ($row = $fetchResult->fetch_assoc()) {
    $productId = $row['id'];
    $qrText = "http://localhost/noble/admin/qrcodeperproduct/view_product.php?id=$productId";

    $resultQR = Builder::create()
        ->writer(new PngWriter())
        ->data($qrText)
        ->size(300)
        ->margin(10)
        ->build();

    $qrBlob = $resultQR->getString(); // Get binary content

    $stmt = $conn->prepare("UPDATE products SET qr_code = ? WHERE id = ?");
    $stmt->bind_param("bi", $null, $productId); // 'b' = blob
    $stmt->send_long_data(0, $qrBlob); // Send blob content
    $stmt->execute();
}


// ✅ Fetch again for display
$displayQuery = "SELECT id, product_name, codename, quantity, price, qr_code FROM products ORDER BY product_name";
$displayResult = $conn->query($displayQuery);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Products with QR</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <?php include '../navbar/top.php'; ?>

    <div class="bg-white p-6 rounded shadow">
        <h2 class="text-2xl font-bold mb-6 text-orange-600">Product List with QR Codes</h2>

        <?php if ($displayResult && $displayResult->num_rows > 0): ?>
            <table class="w-full table-auto border-collapse border border-gray-300 text-sm font-bold text-center">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="border border-gray-300 px-4 py-2">Name</th>
                        <th class="border border-gray-300 px-4 py-2">Codename</th>
                        <th class="border border-gray-300 px-4 py-2">Quantity</th>
                        <th class="border border-gray-300 px-4 py-2">QR Code</th>
                        <th class="border border-gray-300 px-4 py-2">Action</th>

                    </tr>
                </thead>
                <tbody>
                    <?php while ($product = $displayResult->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($product['product_name']) ?></td>
                            <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($product['codename']) ?></td>
                            <td class="border border-gray-300 px-4 py-2"><?= $product['quantity'] ?></td>
                            <td class="border border-gray-300 px-4 py-2 text-center">
                                <?php if (!empty($product['qr_code'])): ?>
                                    <?php $base64QR = base64_encode($product['qr_code']); ?>
                                    <a href="view_product.php?id=<?= $product['id'] ?>" target="_blank">
                                        <img src="data:image/png;base64,<?= $base64QR ?>" class="h-16 w-16 mx-auto mb-2 hover:scale-105 transition-transform" alt="QR Code" />
                                    </a>
                                    <a href="data:image/png;base64,<?= $base64QR ?>" download="qr_<?= $product['id'] ?>.png"
                                        class="inline-block mt-1 bg-green-600 hover:bg-green-700 text-white text-xs px-2 py-1 rounded">
                                        Download
                                    </a>
                            <td class="border border-gray-300 px-4 py-2">
                                <a href="variant_edit.php?id=<?= $product['id'] ?>"
                                    class="inline-block bg-blue-500 hover:bg-blue-600 text-white text-xs px-3 py-1 rounded">
                                    Update
                                </a>
                            </td>

                        <?php else: ?>
                            <span class="text-gray-400 italic">No QR</span>
                        <?php endif; ?>

                        </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-gray-500 text-center">No products found.</p>
        <?php endif; ?>
    </div>
</body>

</html>