<?php
session_name("nobleuser");
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

        $email = $google_user->email;
        $name = $google_user->name;
        $picture = $google_user->picture;
        $remember_token = bin2hex(random_bytes(16));
        $login_method = 'google';

        // Check if user exists
        $stmt = $conn->prepare("SELECT id, login_method FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // New Google user - insert
            $insert = $conn->prepare("INSERT INTO users (name, email, password, remember_token, profile_picture, login_method) VALUES (?, ?, '', ?, ?, ?)");
            $insert->bind_param("sssss", $name, $email, $remember_token, $picture, $login_method);
            $insert->execute();
            $user_id = $insert->insert_id;
        } else {
            // Existing user
            $row = $result->fetch_assoc();
            $user_id = $row['id'];
            $existing_method = $row['login_method'];

            // Optional: Update login_method if blank or previously 'normal'
            if ($existing_method !== 'google') {
                $update = $conn->prepare("UPDATE users SET remember_token = ?, profile_picture = ?, login_method = ? WHERE id = ?");
                $update->bind_param("sssi", $remember_token, $picture, $login_method, $user_id);
            } else {
                $update = $conn->prepare("UPDATE users SET remember_token = ?, profile_picture = ? WHERE id = ?");
                $update->bind_param("ssi", $remember_token, $picture, $user_id);
            }

            $update->execute();
        }

        // Set session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_picture'] = $picture;
        $_SESSION['google_logged_in'] = true;
        $_SESSION['login_success'] = 'Welcome back, ' . htmlspecialchars($name) . '!';

        // Remember token
        setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), "/");

        header("Location: otherpage/index.php");
        exit;
    } else {
        $_SESSION['login_needed'] = 'Google login failed.';
        header("Location: otherpage/index.php");
        exit;
    }
} else {
    $_SESSION['login_needed'] = 'Please sign in first.';
    header("Location: otherpage/index.php");
    exit;
}
