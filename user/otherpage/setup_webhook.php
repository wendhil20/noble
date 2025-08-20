<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';

// Bot Token - SAME AS YOUR WEBHOOK FILE
$bot_token = "8351057197:AAEMUjbb39t4pkBX56eZVjG2cv5oxTWDrh8";

// Your webhook URL - CHANGE THIS TO YOUR ACTUAL URL
// Examples:
// For ngrok: https://abc123.ngrok-free.app/path/to/your/webhook.php
// For live server: https://yourdomain.com/bot/webhook.php
$webhook_url = "http://localhost/noble/user/otherpage/setup_webhook.php";

echo "<h1>Telegram Bot Webhook Setup</h1>";

// Function to make API calls
function callTelegramAPI($bot_token, $method, $data = []) {
    $url = "https://api.telegram.org/bot{$bot_token}/{$method}";
    
    if (!empty($data)) {
        $url .= "?" . http_build_query($data);
    }
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    return json_decode($response, true);
}

// Check if we want to delete webhook first
if (isset($_GET['delete'])) {
    echo "<h2>Deleting Current Webhook...</h2>";
    $delete_result = callTelegramAPI($bot_token, 'deleteWebhook');
    echo "<pre>" . json_encode($delete_result, JSON_PRETTY_PRINT) . "</pre>";
    echo "<br><a href='?'>Setup New Webhook</a>";
    exit;
}

// Validate webhook URL
if ($webhook_url === "http://localhost/noble/user/otherpage/setup_webhook.php") {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "<strong>⚠️ WARNING:</strong> You need to change the webhook URL to your actual domain!<br>";
    echo "Update the \$webhook_url variable in this file.";
    echo "</div>";
    exit;
}

// Validate webhook URL format
if (!filter_var($webhook_url, FILTER_VALIDATE_URL) || !preg_match('/^https:\/\//', $webhook_url)) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "<strong>❌ ERROR:</strong> Webhook URL must be a valid HTTPS URL<br>";
    echo "Current URL: " . htmlspecialchars($webhook_url);
    echo "</div>";
    exit;
}

echo "<h2>Setting Up Webhook...</h2>";
echo "<p><strong>Webhook URL:</strong> " . htmlspecialchars($webhook_url) . "</p>";

// Set webhook
$webhook_data = [
    'url' => $webhook_url,
    'drop_pending_updates' => 'true', // Clear old updates
    'allowed_updates' => json_encode(['message', 'callback_query']) // Only handle messages
];

$result = callTelegramAPI($bot_token, 'setWebhook', $webhook_data);

echo "<h3>Setup Result:</h3>";
if ($result['ok']) {
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "<strong>✅ SUCCESS:</strong> Webhook set successfully!<br>";
    echo "Description: " . htmlspecialchars($result['description']);
    echo "</div>";
} else {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "<strong>❌ ERROR:</strong> Failed to set webhook<br>";
    echo "Error: " . htmlspecialchars($result['description'] ?? 'Unknown error');
    echo "</div>";
}

echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";

// Get current webhook info
echo "<h2>Current Webhook Information:</h2>";
$webhook_info = callTelegramAPI($bot_token, 'getWebhookInfo');

if ($webhook_info['ok']) {
    $info = $webhook_info['result'];
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Property</th><th>Value</th></tr>";
    echo "<tr><td>URL</td><td>" . htmlspecialchars($info['url'] ?? 'Not set') . "</td></tr>";
    echo "<tr><td>Has Custom Certificate</td><td>" . ($info['has_custom_certificate'] ? 'Yes' : 'No') . "</td></tr>";
    echo "<tr><td>Pending Updates</td><td>" . ($info['pending_update_count'] ?? 0) . "</td></tr>";
    echo "<tr><td>Last Error Date</td><td>" . 
         (isset($info['last_error_date']) ? date('Y-m-d H:i:s', $info['last_error_date']) : 'None') . 
         "</td></tr>";
    echo "<tr><td>Last Error Message</td><td>" . 
         htmlspecialchars($info['last_error_message'] ?? 'None') . 
         "</td></tr>";
    echo "<tr><td>Max Connections</td><td>" . ($info['max_connections'] ?? 'Default') . "</td></tr>";
    echo "<tr><td>Allowed Updates</td><td>" . 
         (isset($info['allowed_updates']) ? implode(', ', $info['allowed_updates']) : 'All') . 
         "</td></tr>";
    echo "</table>";
    
    // Show warnings if any
    if ($info['pending_update_count'] > 0) {
        echo "<div style='color: orange; padding: 10px; border: 1px solid orange; margin: 10px 0;'>";
        echo "<strong>⚠️ WARNING:</strong> There are {$info['pending_update_count']} pending updates. ";
        echo "Your webhook might not be responding properly.";
        echo "</div>";
    }
    
    if (!empty($info['last_error_message'])) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>❌ LAST ERROR:</strong> " . htmlspecialchars($info['last_error_message']) . "<br>";
        echo "<strong>Time:</strong> " . date('Y-m-d H:i:s', $info['last_error_date']);
        echo "</div>";
    }
    
} else {
    echo "<pre>" . json_encode($webhook_info, JSON_PRETTY_PRINT) . "</pre>";
}

// Test bot info
echo "<h2>Bot Information:</h2>";
$bot_info = callTelegramAPI($bot_token, 'getMe');

if ($bot_info['ok']) {
    $bot = $bot_info['result'];
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Property</th><th>Value</th></tr>";
    echo "<tr><td>Bot Name</td><td>" . htmlspecialchars($bot['first_name']) . "</td></tr>";
    echo "<tr><td>Username</td><td>@" . htmlspecialchars($bot['username']) . "</td></tr>";
    echo "<tr><td>Bot ID</td><td>" . $bot['id'] . "</td></tr>";
    echo "<tr><td>Can Read Groups</td><td>" . ($bot['can_read_all_group_messages'] ?? false ? 'Yes' : 'No') . "</td></tr>";
    echo "</table>";
} else {
    echo "<div style='color: red;'>Failed to get bot information</div>";
}

echo "<h2>Actions:</h2>";
echo "<p>";
echo "<a href='?' style='background: blue; color: white; padding: 10px; text-decoration: none; margin: 5px;'>🔄 Refresh Status</a> ";
echo "<a href='?delete=1' style='background: red; color: white; padding: 10px; text-decoration: none; margin: 5px;' onclick='return confirm(\"Are you sure you want to delete the webhook?\")'>🗑️ Delete Webhook</a>";
echo "</p>";

echo "<h2>Testing Your Bot:</h2>";
if ($webhook_info['ok'] && !empty($webhook_info['result']['url'])) {
    echo "<p>Your bot is ready! You can now:</p>";
    echo "<ol>";
    echo "<li>Go to Telegram and search for <strong>@" . htmlspecialchars($bot_info['result']['username'] ?? 'your_bot') . "</strong></li>";
    echo "<li>Send <code>/start</code> to begin</li>";
    echo "<li>Try searching for products by typing product names</li>";
    echo "</ol>";
} else {
    echo "<p style='color: red;'>Webhook is not properly configured. Please fix the issues above.</p>";
}

echo "<h2>Troubleshooting:</h2>";
echo "<ul>";
echo "<li><strong>SSL Certificate:</strong> Make sure your webhook URL uses HTTPS with a valid SSL certificate</li>";
echo "<li><strong>File Permissions:</strong> Ensure your webhook.php file has proper read permissions</li>";
echo "<li><strong>Error Logs:</strong> Check your bot_log.txt file for error messages</li>";
echo "<li><strong>Database:</strong> Verify your database connection is working</li>";
echo "</ul>";
?>