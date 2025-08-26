<?php
// chatbot_api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include '../../connection/connect.php';

// Load API Key (put this in .env or config file in real system)
$apiKey = "AIzaSyB5DtWDZE_NNBJIEgLooOkGjqRCvLtLE9Q";

// Get user question
$data = json_decode(file_get_contents("php://input"), true);
$userQuestion = $data['question'] ?? "";

if (empty($userQuestion)) {
    echo json_encode(['error' => 'No question provided']);
    exit;
}

try {
    // Get enhanced product data with images and variants
    $sql = "SELECT 
                p.id, 
                p.product_name, 
                p.description, 
                p.price,
                p.quantity,
                p.main_image,
                p.sub_images,
                p.descrip1,
                p.descrip2,
                p.descrip3,
                p.descrip4,
                p.descrip5,
                p.descrip6,
                p.descrip7,
                p.descrip8,
                p.descrip9,
                p.descrip10,
                p.category_id,
                GROUP_CONCAT(DISTINCT CONCAT(pc.color_name, ':', pc.color_code, ':', pc.price) SEPARATOR '|') as colors,
                GROUP_CONCAT(DISTINCT CONCAT(pv.color, ':', pv.size, ':', pv.price, ':', pv.image) SEPARATOR '|') as variants
            FROM products p
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            GROUP BY p.id
            LIMIT 30";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    // Build comprehensive context with images
    $productContext = "Available Products with Details:\n\n";
    foreach ($products as $p) {
        $productContext .= "=== PRODUCT ID: {$p['id']} ===\n";
        $productContext .= "Name: {$p['product_name']}\n";
        $productContext .= "Price: ₱{$p['price']}\n";
        $productContext .= "Stock: {$p['quantity']}\n";
        $productContext .= "Description: {$p['description']}\n";
        
        // Add main image if available
        if (!empty($p['main_image'])) {
            $productContext .= "Main Image: {$p['main_image']}\n";
        }
        
        // Add sub images if available
        if (!empty($p['sub_images'])) {
            $productContext .= "Additional Images: {$p['sub_images']}\n";
        }
        
        // Add detailed descriptions
        $descriptions = [];
        for ($i = 1; $i <= 10; $i++) {
            if (!empty($p["descrip$i"])) {
                $descriptions[] = $p["descrip$i"];
            }
        }
        if (!empty($descriptions)) {
            $productContext .= "Features: " . implode(", ", $descriptions) . "\n";
        }
        
        // Add color variants
        if (!empty($p['colors'])) {
            $productContext .= "Available Colors: ";
            $colors = explode('|', $p['colors']);
            $colorInfo = [];
            foreach ($colors as $color) {
                $colorParts = explode(':', $color);
                if (count($colorParts) >= 3) {
                    $colorInfo[] = "{$colorParts[0]} (₱{$colorParts[2]})";
                }
            }
            $productContext .= implode(", ", $colorInfo) . "\n";
        }
        
        // Add size/variant info
        if (!empty($p['variants'])) {
            $productContext .= "Variants: ";
            $variants = explode('|', $p['variants']);
            $variantInfo = [];
            foreach ($variants as $variant) {
                $variantParts = explode(':', $variant);
                if (count($variantParts) >= 3) {
                    $info = "{$variantParts[0]}";
                    if (!empty($variantParts[1])) $info .= " Size: {$variantParts[1]}";
                    $info .= " (₱{$variantParts[2]})";
                    if (!empty($variantParts[3])) $info .= " [Image: {$variantParts[3]}]";
                    $variantInfo[] = $info;
                }
            }
            $productContext .= implode(", ", $variantInfo) . "\n";
        }
        
        $productContext .= "\n";
    }
    
    // Enhanced prompt with instructions for image handling
    $prompt = "You are a helpful product assistant for an e-commerce store. Answer the user's question using the product data below. 

IMPORTANT INSTRUCTIONS:
1. When mentioning products, include relevant details like price, availability, and features
2. If a product has images, mention them and provide the image paths/URLs when relevant
3. Be conversational and helpful
4. If asked about specific colors or variants, mention the available options and their prices
5. If no exact match is found, suggest similar products
6. Always be polite and informative
7. Format prices in Philippine Peso (₱)
8. When showing product images, format them as: ![Product Name](image_path)

Product Database:\n" . $productContext . "\n\nUser's question: $userQuestion\n\nPlease provide a helpful response:";
    
    // Call Gemini API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=$apiKey");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "topP" => 0.8,
            "topK" => 40,
            "maxOutputTokens" => 1024
        ]
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        throw new Exception("Curl error: " . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("API returned HTTP code: $httpCode");
    }
    
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from API");
    }
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => 'Sorry, I encountered an error while processing your request. Please try again.']
                    ]
                ]
            ]
        ]
    ]);
}