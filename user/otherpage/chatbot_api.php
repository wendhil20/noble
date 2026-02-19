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
    // Check if user is asking for specific product by name or ID
    $specificProductCondition = "";
    $isSpecificSearch = false;
    
    // Look for product names or IDs in the user question
    if (preg_match('/\b(product|item)\s*(\d+)\b/i', $userQuestion, $matches)) {
        $specificProductCondition = "WHERE p.id = " . intval($matches[2]);
        $isSpecificSearch = true;
    } elseif (preg_match('/"([^"]+)"/', $userQuestion, $matches)) {
        $specificProductCondition = "WHERE p.product_name LIKE '%" . $conn->real_escape_string($matches[1]) . "%'";
        $isSpecificSearch = true;
    } elseif (strlen($userQuestion) > 10) {
        // Try to find product names that might be mentioned
        $words = explode(' ', $userQuestion);
        $searchTerms = array_filter($words, function($word) {
            return strlen($word) > 3; // Only consider words longer than 3 characters
        });
        
        if (!empty($searchTerms)) {
            $searchConditions = [];
            foreach ($searchTerms as $term) {
                $searchConditions[] = "p.product_name LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "p.description LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "p.codename LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "p.descrip6 LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "p.descrip7 LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "pc.color_name LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "pt.type_name LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "pv.color LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "pv.size LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "pv.status LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "pv.origin LIKE '%" . $conn->real_escape_string($term) . "%'";
                $searchConditions[] = "pv.subcategory_name LIKE '%" . $conn->real_escape_string($term) . "%'";
                
                // Special handling for discount searches
                if (stripos($term, 'discount') !== false || stripos($term, 'sale') !== false) {
                    $searchConditions[] = "pv.discount > 0";
                }
            }
            if (!empty($searchConditions)) {
                $specificProductCondition = "WHERE (" . implode(' OR ', $searchConditions) . ")";
                $isSpecificSearch = true;
            }
        }
    }
    
    // Get essential product data based on actual database structure
    $sql = "SELECT 
                p.id, 
                p.product_name, 
                p.codename,
                p.description, 
                p.descrip6,
                p.descrip7,
                GROUP_CONCAT(DISTINCT pc.color_name SEPARATOR '|') as colors,
                GROUP_CONCAT(DISTINCT CONCAT(pv.color, ':', pv.size, ':', pv.original_price, ':', pv.price, ':', pv.discount, ':', pv.status, ':', pv.origin, ':', pv.subcategory_name) SEPARATOR '|') as variants,
                GROUP_CONCAT(DISTINCT CONCAT(pt.type_name, ':', pt.rating) SEPARATOR '|') as product_types
            FROM products p
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_types pt ON p.id = pt.product_id
            $specificProductCondition
            GROUP BY p.id
            LIMIT " . ($isSpecificSearch ? "10" : "5");
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    // Build compact context using actual database structure
    $productContext = "Available Products:\n\n";
    foreach ($products as $p) {
        $productContext .= "=== PRODUCT ID: {$p['id']} ===\n";
        $productContext .= "Name: {$p['product_name']}\n";
        
        if (!empty($p['codename'])) {
            $productContext .= "Category: {$p['codename']}\n";
        }
        
        if (!empty($p['description'])) {
            $productContext .= "Description: {$p['description']}\n";
        }
        
        if (!empty($p['descrip6'])) {
            $productContext .= "Details: {$p['descrip6']}\n";
        }
        
        if (!empty($p['descrip7'])) {
            $productContext .= "Additional Info: {$p['descrip7']}\n";
        }
        
        if (!empty($p['subcategory_name'])) {
            $productContext .= "Subcategory: {$p['subcategory_name']}\n";
        }
        
        // Add available colors
        if (!empty($p['colors'])) {
            $productContext .= "Available Colors: {$p['colors']}\n";
        }
        
        // Add product types with ratings
        if (!empty($p['product_types'])) {
            $productContext .= "Product Types: ";
            $types = explode('|', $p['product_types']);
            $typeInfo = [];
            foreach ($types as $type) {
                $typeParts = explode(':', $type);
                if (count($typeParts) >= 2) {
                    $typeInfo[] = "{$typeParts[0]} (Rating: {$typeParts[1]})";
                } else {
                    $typeInfo[] = $typeParts[0];
                }
            }
            $productContext .= implode(", ", $typeInfo) . "\n";
        }
        
        // Add variants with complete information
        if (!empty($p['variants'])) {
            $productContext .= "Variants: ";
            $variants = explode('|', $p['variants']);
            $variantInfo = [];
            foreach ($variants as $variant) {
                $variantParts = explode(':', $variant);
                if (count($variantParts) >= 8) {
                    $info = "";
                    if (!empty($variantParts[0])) $info .= "Color: {$variantParts[0]}, ";
                    if (!empty($variantParts[1])) $info .= "Size: {$variantParts[1]}, ";
                    if (!empty($variantParts[2])) $info .= "Original: ₱{$variantParts[2]}, ";
                    if (!empty($variantParts[3])) $info .= "Price: ₱{$variantParts[3]}, ";
                    if (!empty($variantParts[4]) && $variantParts[4] > 0) $info .= "Discount: ₱{$variantParts[4]}, ";
                    if (!empty($variantParts[5])) $info .= "Status: {$variantParts[5]}, ";
                    if (!empty($variantParts[6])) $info .= "Origin: {$variantParts[6]}, ";
                    if (!empty($variantParts[7])) $info .= "Subcategory: {$variantParts[7]}";
                    $variantInfo[] = rtrim($info, ', ');
                }
            }
            $productContext .= implode(" | ", $variantInfo) . "\n";
        }
        
        $productContext .= "\n";
    }
    
    // Simplified prompt without image instructions
    $prompt = "You are a helpful product assistant for an e-commerce store. Answer the user's question using the product data below. 

INSTRUCTIONS:
1. Include relevant details like price, availability, and features
2. Be conversational and helpful
3. If asked about colors or variants, mention available options and prices
4. If no exact match found, suggest similar products
5. Always be polite and informative
6. Format prices in Philippine Peso (₱)

Product Database:\n" . $productContext . "\n\nUser's question: $userQuestion\n\nResponse:";
    
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
            "maxOutputTokens" => 800
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