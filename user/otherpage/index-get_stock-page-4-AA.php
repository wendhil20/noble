<?php
// ../api/get_stock.php
header('Content-Type: application/json');
include '../../connection/connect.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$variant_id = (int)($data['variant_id'] ?? 0);
$color_id = (int)($data['color_id'] ?? 0);

if (!$variant_id || !$color_id) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => 'Missing variant_id or color_id',
    'stock' => 0
  ]);
  exit;
}

try {
  // Query junction table for stock
  $stmt = $conn->prepare("
    SELECT stock_quantity 
    FROM product_variant_colors 
    WHERE variant_id = ? AND color_id = ?
    LIMIT 1
  ");
  
  $stmt->bind_param("ii", $variant_id, $color_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $stock = 0;
  
  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stock = (int)$row['stock_quantity'];
  } else {
    // No combination found - assume out of stock
    $stock = 0;
  }
  
  $stmt->close();
  
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'stock' => $stock,
    'variant_id' => $variant_id,
    'color_id' => $color_id
  ]);
  
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Database error: ' . $e->getMessage(),
    'stock' => 0
  ]);
}