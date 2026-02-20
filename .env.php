<?php
/**
 * .env.php - Environment Variable Loader
 * Include this file: require_once '../../.env.php';
 * Then use: getenv('PAYMONGO_SECRET_KEY')
 */

$env_file = dirname(__DIR__) . '/.env';

// Check if .env file exists
if (!file_exists($env_file)) {
    // Silently fail or log warning - don't break the app
    error_log("Warning: .env file not found at: $env_file");
    return;
}

// Read and parse .env file
$lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    // Skip comments and empty lines
    if (empty($line) || strpos(trim($line), '#') === 0) {
        continue;
    }

    // Parse KEY=VALUE
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        
        $key = trim($key);
        $value = trim($value);
        
        // Remove quotes if present
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        // Set as environment variable
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
?>