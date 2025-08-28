<?php
// products.php
header('Content-Type: application/json');
include 'connection/connect.php';
// DEBUG VERSION - Add this temporarily to see what's happening
$size_q = $conn->prepare("SELECT size FROM product_variants WHERE product_id = ? AND size IS NOT NULL AND size != '' AND TRIM(size) != ''");
$size_q->bind_param("i", $product_id);
$size_q->execute();
$size_result = $size_q->get_result();

// DEBUG: Print all size records for this product
echo "<!-- DEBUG: Product ID $product_id -->";
$all_size_records = [];
while ($size_row = $size_result->fetch_assoc()) {
    $all_size_records[] = $size_row['size'];
    echo "<!-- Size record: " . htmlspecialchars($size_row['size']) . " -->";
}

// Reset the result
$size_q->execute();
$size_result = $size_q->get_result();

$size_count = 0;
$all_sizes = array();

// Process all size records
while ($size_row = $size_result->fetch_assoc()) {
    $size_string = $size_row['size'];
    echo "<!-- Processing: $size_string -->";
    
    // Split by different possible separators
    $separators = ['&', '|', ';', ',', PHP_EOL, "\n", "\r\n"];
    $found_separator = false;
    
    foreach ($separators as $sep) {
        if (strpos($size_string, $sep) !== false) {
            $size_components = explode($sep, $size_string);
            $found_separator = true;
            echo "<!-- Found separator '$sep', components: " . count($size_components) . " -->";
            
            foreach ($size_components as $component) {
                $trimmed_component = trim($component);
                if ($trimmed_component != '') {
                    $all_sizes[] = $trimmed_component;
                    echo "<!-- Added size: $trimmed_component -->";
                }
            }
            break;
        }
    }
    
    // If no separator found, treat as single size
    if (!$found_separator) {
        $trimmed_size = trim($size_string);
        if ($trimmed_size != '') {
            $all_sizes[] = $trimmed_size;
            echo "<!-- Added single size: $trimmed_size -->";
        }
    }
}

// Remove duplicates and count
$unique_sizes = array_unique($all_sizes);
$size_count = count($unique_sizes);

echo "<!-- Total unique sizes: $size_count -->";
echo "<!-- Unique sizes: " . implode(', ', $unique_sizes) . " -->";

$size_q->close();
?>