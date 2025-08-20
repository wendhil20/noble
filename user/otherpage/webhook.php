<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../../connection/connect.php';

// Bot Token - MOVE TO CONFIG FILE IN PRODUCTION!
$bot_token = "8351057197:AAEMUjbb39t4pkBX56eZVjG2cv5oxTWDrh8";

// Main webhook handler
try {
    // Log webhook call
    logMessage("Webhook called");
    
    // Get and validate input
    $input = file_get_contents('php://input');
    if (!$input) {
        logMessage("No input received");
        exit;
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("JSON decode error: " . json_last_error_msg());
        exit;
    }
    
    // Extract message data safely
    $message = isset($data['message']['text']) ? trim($data['message']['text']) : '';
    $chat_id = isset($data['message']['chat']['id']) ? $data['message']['chat']['id'] : '';
    $user_name = isset($data['message']['from']['first_name']) ? $data['message']['from']['first_name'] : 'User';
    $user_id = isset($data['message']['from']['id']) ? $data['message']['from']['id'] : 0;
    
    // Process message if valid
    if ($message && $chat_id) {
        $response = processMessage($message, $user_name, $conn);
        sendMessage($chat_id, $response, $bot_token);
    }
    
} catch (Exception $e) {
    logMessage("Critical error: " . $e->getMessage());
}

function processMessage($message, $user_name, $conn) {
    $original_message = $message;
    $message_lower = strtolower(trim($message));
    
    // Handle commands
    switch (true) {
        case $message_lower === '/start':
            return getWelcomeMessage($user_name);
            
        case $message_lower === '/help':
            return getHelpMessage();
            
        case $message_lower === '/categories':
            return getCategoriesMessage($conn);
            
        case $message_lower === '/products':
            return getAllProductsMessage($conn);
            
        case $message_lower === '/stock':
            return getStockMessage($conn);
            
        case strpos($message_lower, '/search') === 0:
            return handleSearchCommand($message_lower, $conn);
            
        default:
            return handleGeneralSearch($original_message, $conn);
    }
}

function getWelcomeMessage($user_name) {
    $safe_name = htmlspecialchars($user_name);
    return "🤖 Hello {$safe_name}! Welcome to Noble Home Store!\n\n" .
           "I can help you with:\n" .
           "• 🛍️ Product search - just type product name\n" .
           "• 📦 /products - see all products\n" .
           "• 📊 /stock - check inventory\n" .
           "• 🔍 /search [name] - detailed search\n" .
           "• 📱 /categories - browse by category\n" .
           "• ❓ /help - show all commands\n\n" .
           "Try typing 'iPhone' or 'laptop'!";
}

function getHelpMessage() {
    return "🆘 Available Commands:\n\n" .
           "📋 Basic Commands:\n" .
           "• /start - Welcome message\n" .
           "• /help - This help menu\n\n" .
           "🛍️ Product Commands:\n" .
           "• /products - List all products\n" .
           "• /categories - Browse categories\n" .
           "• /stock - Check inventory levels\n" .
           "• /search [name] - Detailed product search\n\n" .
           "💡 Quick Tips:\n" .
           "• Just type any product name for quick search\n" .
           "• Use /search for more detailed info";
}

function getCategoriesMessage($conn) {
    try {
        $stmt = $conn->prepare("SELECT category, COUNT(*) as count FROM products WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY category");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response = "📂 Product Categories:\n\n";
            while ($row = $result->fetch_assoc()) {
                $category = htmlspecialchars($row['category']);
                $response .= "📁 {$category} ({$row['count']} items)\n";
            }
            $response .= "\nType category name to see products!";
            return $response;
        } else {
            return "No categories found in our database.";
        }
    } catch (Exception $e) {
        logMessage("Error in getCategoriesMessage: " . $e->getMessage());
        return "Sorry, I couldn't retrieve categories right now. Please try again later.";
    }
}

function getAllProductsMessage($conn) {
    try {
        $stmt = $conn->prepare("SELECT name, price, stock FROM products WHERE name IS NOT NULL ORDER BY name LIMIT 20");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response = "📦 Our Products:\n\n";
            while ($row = $result->fetch_assoc()) {
                $stock_icon = $row['stock'] > 0 ? "✅" : "❌";
                $name = htmlspecialchars($row['name']);
                $price = number_format($row['price'], 2);
                $response .= "{$stock_icon} {$name} - ₱{$price}\n";
            }
            $response .= "\nType product name for more details!";
            return $response;
        } else {
            return "No products found in database.";
        }
    } catch (Exception $e) {
        logMessage("Error in getAllProductsMessage: " . $e->getMessage());
        return "Sorry, I couldn't retrieve products right now. Please try again later.";
    }
}

function getStockMessage($conn) {
    try {
        $stmt = $conn->prepare("SELECT name, stock FROM products WHERE name IS NOT NULL ORDER BY stock DESC LIMIT 15");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response = "📊 Stock Levels:\n\n";
            while ($row = $result->fetch_assoc()) {
                $status = $row['stock'] > 5 ? "✅" : ($row['stock'] > 0 ? "⚠️" : "❌");
                $name = htmlspecialchars($row['name']);
                $response .= "{$status} {$name} - {$row['stock']} pcs\n";
            }
            return $response;
        } else {
            return "No stock data available.";
        }
    } catch (Exception $e) {
        logMessage("Error in getStockMessage: " . $e->getMessage());
        return "Sorry, I couldn't retrieve stock information right now.";
    }
}

function handleSearchCommand($message, $conn) {
    $search_term = trim(str_replace('/search', '', $message));
    
    if (empty($search_term)) {
        return "Please specify what to search for.\nExample: /search iPhone";
    }
    
    return searchProducts($search_term, $conn, true);
}

function searchProducts($search_term, $conn, $detailed = false) {
    try {
        $search_term = trim($search_term);
        if (empty($search_term)) {
            return "Please enter a product name to search for.";
        }
        
        $stmt = $conn->prepare("SELECT * FROM products WHERE name LIKE ? OR description LIKE ? ORDER BY name LIMIT 10");
        $like_term = "%{$search_term}%";
        $stmt->bind_param("ss", $like_term, $like_term);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response = "🔍 Search Results for '" . htmlspecialchars($search_term) . "':\n\n";
            
            while ($row = $result->fetch_assoc()) {
                $stock_status = $row['stock'] > 0 ? "✅ In Stock ({$row['stock']} pcs)" : "❌ Out of Stock";
                $name = htmlspecialchars($row['name']);
                $price = number_format($row['price'], 2);
                
                $response .= "📱 {$name}\n";
                $response .= "💰 Price: ₱{$price}\n";
                $response .= "📦 {$stock_status}\n";
                
                if ($detailed && !empty($row['description'])) {
                    $desc = htmlspecialchars(substr($row['description'], 0, 150));
                    $response .= "📝 {$desc}...\n";
                }
                
                if (!empty($row['category'])) {
                    $category = htmlspecialchars($row['category']);
                    $response .= "🏷️ Category: {$category}\n";
                }
                $response .= "\n";
            }
            return $response;
        } else {
            return "Sorry, no products found matching '" . htmlspecialchars($search_term) . "' 😞\n\n" .
                   "Try:\n" .
                   "• /products - see all available items\n" .
                   "• /categories - browse by category";
        }
    } catch (Exception $e) {
        logMessage("Error in searchProducts: " . $e->getMessage());
        return "Sorry, search is temporarily unavailable. Please try again later.";
    }
}

function handleGeneralSearch($original_message, $conn) {
    // First try exact category match
    $categories = ['mobile', 'laptop', 'tablet', 'audio', 'gaming', 'accessories'];
    $message_lower = strtolower($original_message);
    
    foreach ($categories as $category) {
        if (strpos($message_lower, $category) !== false) {
            return searchByCategory($category, $conn);
        }
    }
    
    // Then try general product search
    $result = searchProducts($original_message, $conn);
    
    // If no results, try partial word search
    if (strpos($result, 'no products found') !== false) {
        return tryPartialSearch($original_message, $conn);
    }
    
    return $result;
}

function searchByCategory($category, $conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM products WHERE LOWER(category) LIKE ? ORDER BY name LIMIT 10");
        $like_category = "%{$category}%";
        $stmt->bind_param("s", $like_category);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response = "📱 " . ucfirst($category) . " Products:\n\n";
            while ($row = $result->fetch_assoc()) {
                $stock_status = $row['stock'] > 0 ? "✅" : "❌";
                $name = htmlspecialchars($row['name']);
                $price = number_format($row['price'], 2);
                $response .= "{$stock_status} {$name} - ₱{$price}\n";
            }
            $response .= "\nType '/search [product]' for detailed info!";
            return $response;
        }
    } catch (Exception $e) {
        logMessage("Error in searchByCategory: " . $e->getMessage());
    }
    
    return "No products found in " . htmlspecialchars($category) . " category.";
}

function tryPartialSearch($original_message, $conn) {
    try {
        $words = array_filter(explode(' ', $original_message), function($word) {
            return strlen(trim($word)) > 2;
        });
        
        if (empty($words)) {
            return getDefaultResponse();
        }
        
        $conditions = [];
        $params = [];
        foreach ($words as $word) {
            $conditions[] = "(name LIKE ? OR description LIKE ?)";
            $params[] = "%{$word}%";
            $params[] = "%{$word}%";
        }
        
        $query = "SELECT * FROM products WHERE " . implode(' OR ', $conditions) . " LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response = "Maybe you're looking for:\n\n";
            while ($row = $result->fetch_assoc()) {
                $name = htmlspecialchars($row['name']);
                $price = number_format($row['price'], 2);
                $response .= "• {$name} - ₱{$price}\n";
            }
            $response .= "\nType '/search [product name]' for more details!";
            return $response;
        }
    } catch (Exception $e) {
        logMessage("Error in tryPartialSearch: " . $e->getMessage());
    }
    
    return getDefaultResponse();
}

function getDefaultResponse() {
    return "I'm not sure what you're looking for. 😊\n\n" .
           "Try:\n" .
           "• Type a product name (iPhone, laptop, etc.)\n" .
           "• /products - see all products\n" .
           "• /categories - browse categories\n" .
           "• /help - see available commands";
}

function sendMessage($chat_id, $text, $bot_token) {
    try {
        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        logMessage("Message sent successfully");
        return $result;
        
    } catch (Exception $e) {
        logMessage("Error sending message: " . $e->getMessage());
        return false;
    }
}

function logMessage($message) {
    try {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents('bot_log.txt', "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // Fail silently if logging doesn't work
    }
}
?>