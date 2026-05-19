<?php
// fetch_categories.php
include ROOT_PATH . '/connection/connect.php';

// Fetch categories from database
$query = "SELECT id, name, image_pathtwo FROM categories ORDER BY id";
$result = mysqli_query($conn, $query);
$categories = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Category URL mapping
$categoryUrlMap = [
    'furniture' => 'furniture',
    'Tiles' => 'tiles',
    'Bedfurniture' => 'bedfurniture',
    'BathroomFixtures' => 'bathroom',
    'AACBlock' => 'aacblock',
    'aircon' => 'aircon',
    'KitchenFixtures' => 'kitchen',
    'lightingfixture' => 'lighting',
    'Doors' => 'doors',
    'windows' => 'windows',
    'buildingmaterials' => 'buildingmaterials'
];

// Function to format display name
function formatCategoryName($categoryName) {
    $nameMap = [
        'BathroomFixtures' => 'Bathroom',
        'KitchenFixtures' => 'Kitchen Fixtures',
        'lightingfixture' => 'Lighting',
        'Bedfurniture' => 'Bedroom',
        'buildingmaterials' => 'Building Materials',
        'AACBlock' => 'AAC Block'
    ];
    
    return $nameMap[$categoryName] ?? ucfirst($categoryName);
}
?>