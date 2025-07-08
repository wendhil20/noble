<?php
session_start();

require_once '../vendor/autoload.php';
require_once '../connection/connect.php';

use Google\Service\Oauth2;

$client = new Google_Client();
$client->setClientId('465138054143-qv9j0hfr0ft416r41qj1qsqvl1u726u0.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/noble/user/google-callback.php');
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);
        $google_oauth = new Oauth2($client);
        $google_user = $google_oauth->userinfo->get();

        // Save user data
        $email = $google_user->email;
        $name = $google_user->name;
        $remember_token = bin2hex(random_bytes(16));

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO users (name, email, password, remember_token) VALUES (?, ?, '', ?)");
            $insert->bind_param("sss", $name, $email, $remember_token);
            $insert->execute();
            $user_id = $insert->insert_id;
        } else {
            $row = $result->fetch_assoc();
            $user_id = $row['id'];
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['google_logged_in'] = true;
        setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), "/");

        header("Location: index.php");
        exit;
    } else {
        // Set a notification message in session for "Please log in"
        $_SESSION['login_needed'] = 'You need to log in to access this page.';
        header("Location: index.php");  // Redirect to the homepage (or the page you want)
        exit;
    }
} else {
    // Set a notification message if no code is received
    $_SESSION['login_needed'] = 'You need to log in to access this page.';
    header("Location: index.php");  // Redirect to the homepage (or the page you want)
    exit;
}

