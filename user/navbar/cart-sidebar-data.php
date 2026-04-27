<?php
session_name("nobleuser");
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__, 2));
include ROOT_PATH . '/connection/connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$items = [];
$total = 0;

if ($user_id) {
    $stmt = $conn->prepare("
        SELECT 
            c.id, c.quantity, c.price, c.codename,
            c.variant_name, c.type_name, c.size, c.color_name,
            t.type_image, p.product_name, p.main_image
        FROM user_cart_items c
        LEFT JOIN product_types t 
            ON t.product_id = c.product_id AND t.type_name = c.type_name
        LEFT JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY c.id DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $subtotal = floatval($row['price']) * intval($row['quantity']);
        $total += $subtotal;

        $details = array_filter([
            $row['variant_name'] ?? null,
            $row['size'] ?? null,
            $row['color_name'] ?? null
        ]);

        $image = $row['type_image'] ?? $row['main_image'] ?? null;

        $items[] = [
            'id'       => $row['id'],
            'name'     => $row['codename'] ?? $row['product_name'] ?? '—',
            'details'  => implode(' · ', $details) ?: '—',
            'quantity' => intval($row['quantity']),
            'price'    => floatval($row['price']),
            'subtotal' => $subtotal,
            'image'    => $image,
        ];
    }
    $stmt->close();
}

echo json_encode([
    'logged_in' => (bool)$user_id,
    'count'     => count($items),
    'total'     => $total,
    'items'     => $items,
]);