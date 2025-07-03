<?php
session_start();
require_once '../vendor/autoload.php';
require_once '../connection/connect.php'; // ← Your DB connection

use Google\Service\Oauth2;
use Google_Service_Oauth2;

$client = new Google_Client();
$client->setClientId('');
$client->setClientSecret('');
$client->setRedirectUri('http://localhost/noble/user/google-callback.php');
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);

        // Get user info
        $google_oauth = new Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        $email = $google_account_info->email;
        $name = $google_account_info->name;

        // Optional: generate random token for remember_token
        $remember_token = bin2hex(random_bytes(16));

        // Save to DB if not exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Not yet in DB, insert
            $insert = $conn->prepare("INSERT INTO users (name, email, password, remember_token) VALUES (?, ?, '', ?)");
            $insert->bind_param("sss", $name, $email, $remember_token);
            $insert->execute();
        }

        // Set session
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;

        // Redirect to homepage
        header("Location: index.php");
        exit;
    } else {
        echo "Google Auth Error: " . htmlspecialchars($token['error']);
    }
} else {
    echo "No code returned from Google.";
}
