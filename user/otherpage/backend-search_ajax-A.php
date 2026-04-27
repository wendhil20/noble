<?php
include ROOT_PATH . '/connection/connect.php';
header('Content-Type: application/json; charset=utf-8');

// Force UTF-8
$conn->set_charset("utf8mb4");

$search = trim($_GET['search'] ?? '');

if ($search === '') {
    echo json_encode([]);
    exit;
}

// ✅ Step 1: Try FULLTEXT (smarter fuzzy matching)
$sql = "
    SELECT id, product_name, main_image,
           MATCH(product_name, description, codename) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance
    FROM products
    WHERE MATCH(product_name, description, codename) AGAINST(? IN NATURAL LANGUAGE MODE)
    ORDER BY relevance DESC
    LIMIT 10
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$res = $stmt->get_result();
$results = [];

while ($row = $res->fetch_assoc()) {
    $imgPath = '';
    if (!empty($row['main_image'])) {
        if (filter_var($row['main_image'], FILTER_VALIDATE_URL)) {
            $imgPath = $row['main_image'];
        } else {
            $imgPath = '../../' . ltrim($row['main_image'], '/');
        }
    }
    $row['main_image'] = $imgPath;
    $results[] = $row;
}

// ✅ Step 2: If no result from fulltext, fallback to fuzzy LIKE search
if (empty($results)) {
    $like = "%{$search}%";
    $stmt2 = $conn->prepare("
        SELECT id, product_name, main_image
        FROM products
        WHERE product_name LIKE ? 
           OR description LIKE ?
           OR codename LIKE ?
        LIMIT 10
    ");
    $stmt2->bind_param("sss", $like, $like, $like);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    while ($row = $res2->fetch_assoc()) {
        $imgPath = '';
        if (!empty($row['main_image'])) {
            if (filter_var($row['main_image'], FILTER_VALIDATE_URL)) {
                $imgPath = $row['main_image'];
            } else {
                $imgPath = '../../' . ltrim($row['main_image'], '/');
            }
        }
        $row['main_image'] = $imgPath;
        $results[] = $row;
    }
}

// ✅ Step 3: Optional fallback – use Levenshtein for near matches
if (empty($results)) {
    $searchLower = strtolower($search);
    $levStmt = $conn->query("SELECT id, product_name, main_image FROM products");
    while ($row = $levStmt->fetch_assoc()) {
        $distance = levenshtein($searchLower, strtolower($row['product_name']));
        if ($distance <= 3) { // smaller = closer match
            $imgPath = '';
            if (!empty($row['main_image'])) {
                if (filter_var($row['main_image'], FILTER_VALIDATE_URL)) {
                    $imgPath = $row['main_image'];
                } else {
                    $imgPath = '../../' . ltrim($row['main_image'], '/');
                }
            }
            $row['main_image'] = $imgPath;
            $results[] = $row;
        }
    }
}

echo json_encode($results);
