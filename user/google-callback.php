<?php
session_name("nobleuser");
session_start();

require_once '../vendor/autoload.php';
require_once '../connection/connect.php';

use Google\Service\Oauth2;

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
        $google_oauth = new Oauth2($client);
        $google_user = $google_oauth->userinfo->get();

        $email = $google_user->email;
        $name = $google_user->name;
        $remember_token = bin2hex(random_bytes(16));

        // Check if user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Auto-register new Google user
            $insert = $conn->prepare("INSERT INTO users (name, email, password, remember_token) VALUES (?, ?, '', ?)");
            $insert->bind_param("sss", $name, $email, $remember_token);
            $insert->execute();
            $user_id = $insert->insert_id;
        } else {
            $row = $result->fetch_assoc();
            $user_id = $row['id'];

            // Optionally update remember_token if needed
            $update = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $update->bind_param("si", $remember_token, $user_id);
            $update->execute();
        }

        // Set session and cookie
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['google_logged_in'] = true;

        setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), "/");

        header("Location: index.php");
        exit;
    } else {
        $_SESSION['login_needed'] = 'Google login failed.';
        header("Location: index.php");
        exit;
    }
} else {
    $_SESSION['login_needed'] = 'No Google auth code received.';
    header("Location: index.php");
    exit;
}
