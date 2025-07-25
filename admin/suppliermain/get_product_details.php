<?php
include '../../connection/connect.php';

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    echo "Invalid product ID.";
    exit;
}

$query = "SELECT 
    sp.product_name,
    sp.item_code,
    sp.description,
    sp.category,
    sp.image,
    sp.unit,
    sp.specification,
    spv.id as variant_id,
    spv.color,
    spv.price,
    spv.image as variant_image,
    svs.size,
    svs.stock
FROM supplier_products sp
LEFT JOIN supplier_product_variants spv ON sp.id = spv.product_id
LEFT JOIN supplier_variant_sizes svs ON spv.id = svs.variant_id
WHERE sp.id = ?
ORDER BY spv.color, svs.size";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "No product details found.";
    exit;
}

$productData = [];
while ($row = $result->fetch_assoc()) {
    $productData['product_name'] = $row['product_name'];
    $productData['item_code'] = $row['item_code'];
    $productData['description'] = $row['description'];
    $productData['category'] = $row['category'];
    $productData['image'] = $row['image'];
    $productData['unit'] = $row['unit'];
    $productData['specification'] = $row['specification'];

    $variant_id = $row['variant_id'];
    if (!isset($productData['variants'][$variant_id])) {
        $productData['variants'][$variant_id] = [
            'color' => $row['color'],
            'price' => $row['price'],
            'image' => $row['variant_image'],
            'sizes' => []
        ];
    }

    if (!empty($row['size'])) {
        $productData['variants'][$variant_id]['sizes'][] = [
            'size' => $row['size'],
            'stock' => $row['stock']
        ];
    }
}
?>

<div>
    <p class="text-sm text-gray-600">Item Code: <?php echo htmlspecialchars($productData['item_code']); ?></p>
    <p class="text-lg font-bold"><?php echo htmlspecialchars($productData['product_name']); ?></p>
    <p class="text-gray-700 mt-2"><?php echo nl2br(htmlspecialchars($productData['description'])); ?></p>
    <p class="text-sm text-gray-500 mt-1">Category: <?php echo htmlspecialchars($productData['category']); ?></p>
    <p class="text-sm text-gray-500 mt-1">Unit: <?php echo htmlspecialchars($productData['unit']); ?></p>
    <p class="text-sm text-gray-500 mt-1">Specification: <?php echo htmlspecialchars($productData['specification']); ?></p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
        <?php foreach ($productData['variants'] as $variant): ?>
        <div class="border p-3 rounded shadow">
            <p class="text-sm font-semibold text-gray-700">Color: <?php echo htmlspecialchars($variant['color']); ?></p>
            <p class="text-red-600 font-bold mb-1">₱ <?php echo number_format($variant['price'], 2); ?></p>
            <img src="../uploads/<?php echo htmlspecialchars($variant['image']); ?>" class="w-24 h-24 object-contain rounded mb-2">

            <div class="text-sm text-gray-600">
                Sizes:
                <ul class="list-disc list-inside">
                    <?php foreach ($variant['sizes'] as $size): ?>
                        <li><?php echo htmlspecialchars($size['size']); ?> - Stock: <?php echo intval($size['stock']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
