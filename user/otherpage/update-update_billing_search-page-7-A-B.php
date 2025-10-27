<?php
// search-proxy.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if query parameter is provided
if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing search query parameter']);
    exit;
}

$query = trim($_GET['q']);
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;

// Validate limit
if ($limit < 1 || $limit > 10) {
    $limit = 5;
}

// Build Nominatim search URL with Philippines focus
$searchParams = [
    'format' => 'json',
    'q' => $query . ', Philippines',
    'limit' => $limit,
    'addressdetails' => '1',
    'countrycodes' => 'ph',
    'bounded' => '1',
    'viewbox' => '116.5,4.5,127.5,21.5' // Philippines bounding box
];

$url = "https://nominatim.openstreetmap.org/search?" . http_build_query($searchParams);

// Set up cURL with proper headers
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'Noble Home Address Search/1.0 (your-email@domain.com)', // Replace with your email
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Accept-Language: en'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to search service: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    // Try broader search if Philippines-specific search fails
    if ($httpCode === 403 || $httpCode === 429) {
        // Rate limited or forbidden, try with delay
        sleep(1);
        
        $broadParams = [
            'format' => 'json',
            'q' => $query,
            'limit' => $limit,
            'addressdetails' => '1',
            'countrycodes' => 'ph'
        ];
        
        $broadUrl = "https://nominatim.openstreetmap.org/search?" . http_build_query($broadParams);
        
        $ch2 = curl_init();
        curl_setopt_array($ch2, [
            CURLOPT_URL => $broadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Noble Home Address Search/1.0 (your-email@domain.com)',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en'
            ]
        ]);
        
        $response = curl_exec($ch2);
        $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
    }
    
    if ($httpCode !== 200) {
        http_response_code($httpCode);
        echo json_encode(['error' => 'Search service returned error: ' . $httpCode]);
        exit;
    }
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from search service']);
    exit;
}

// Return the results
echo json_encode($data);
?>