<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    // Get all variants for this product with their dimensions
    $stmt = $conn->prepare("
        SELECT 
            pv.id,
            pv.size,
            pv.width,
            pv.height,
            pv.length,
            pv.dimension_unit
        FROM product_variants pv
        WHERE pv.product_id = ?
        ORDER BY pv.size ASC
    ");
    
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $variants = [];
    while ($row = $result->fetch_assoc()) {
        $variants[] = [
            'id' => $row['id'],
            'size' => $row['size'],
            'width' => floatval($row['width']),
            'height' => floatval($row['height']),
            'length' => floatval($row['length']),
            'dimension_unit' => $row['dimension_unit']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'variants' => $variants
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>