<?php
// Create this file as curl_test.php in your project directory
// Access it via http://localhost/your-project-path/curl_test.php

echo "<h2>cURL Status Check</h2>";

if (function_exists('curl_init')) {
    echo "<p style='color: green;'>✅ cURL is enabled!</p>";
    
    // Test a simple cURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://httpbin.org/get");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $httpCode == 200) {
        echo "<p style='color: green;'>✅ cURL can make HTTP requests successfully!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ cURL is enabled but cannot make HTTP requests. Check your internet connection.</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ cURL is NOT enabled!</p>";
    echo "<p>Please enable cURL extension in php.ini</p>";
}

// Show PHP version and loaded extensions
echo "<h3>PHP Information:</h3>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Loaded Extensions:</strong></p>";
$extensions = get_loaded_extensions();
echo "<ul>";
foreach ($extensions as $ext) {
    if (stripos($ext, 'curl') !== false) {
        echo "<li style='color: green; font-weight: bold;'>$ext</li>";
    } else {
        echo "<li>$ext</li>";
    }
}
echo "</ul>";
?>