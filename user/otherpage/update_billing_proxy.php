<?php
// geocoding-proxy.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if required parameters are provided
if (!isset($_GET['lat']) || !isset($_GET['lon'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing latitude or longitude parameters']);
    exit;
}

$lat = $_GET['lat'];
$lon = $_GET['lon'];

// Validate coordinates
if (!is_numeric($lat) || !is_numeric($lon)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinate format']);
    exit;
}

// Build Nominatim URL
$url = "https://nominatim.openstreetmap.org/reverse?" . http_build_query([
    'format' => 'json',
    'lat' => $lat,
    'lon' => $lon,
    'addressdetails' => '1'
]);

// Set up cURL with proper headers
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'Noble Home Address Geocoder/1.0 (wendhil10@gmail.com)', // Replace with your email
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Accept-Language: en'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to geocoding service']);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'Geocoding service returned error: ' . $httpCode]);
    exit;
}

// Return the response
echo $response;
?>