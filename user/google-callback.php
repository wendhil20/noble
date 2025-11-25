<?php
//google-callback.php
session_name("nobleuser");
session_start();
require_once '../vendor/autoload.php';
require_once '../connection/connect.php';

use Google\Service\PeopleService;

// Build dynamic redirect URI
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . $host . '/noble/user/google-callback.php';

$client = new Google_Client();
$client->setClientId('465138054143-qv9j0hfr0ft416r41qj1qsqvl1u726u0.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");
$client->addScope("https://www.googleapis.com/auth/userinfo.email");
$client->addScope("https://www.googleapis.com/auth/userinfo.profile");

if (isset($_GET['code'])) {
    try {
        // Check for OAuth errors first
        if (isset($_GET['error'])) {
            throw new Exception("OAuth Error: " . $_GET['error'] . " - " . ($_GET['error_description'] ?? 'Unknown error'));
        }
        
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        // Better token error checking
        if (isset($token['error'])) {
            error_log("Token fetch error: " . print_r($token, true));
            throw new Exception("Token Error: " . $token['error'] . " - " . ($token['error_description'] ?? 'Token fetch failed'));
        }
        
        if (!isset($token['access_token'])) {
            error_log("No access token received: " . print_r($token, true));
            throw new Exception("No access token received from Google");
        }
        
        $client->setAccessToken($token['access_token']);
        
        // Use People API (newer and more reliable)
        $service = new PeopleService($client);
        $profile = $service->people->get('people/me', [
            'personFields' => 'names,emailAddresses,photos'
        ]);
        
        // Extract user information
        $emailAddresses = $profile->getEmailAddresses();
        $names = $profile->getNames();
        $photos = $profile->getPhotos();
        
        $email = $emailAddresses && count($emailAddresses) > 0 ? $emailAddresses[0]->getValue() : '';
        $name = $names && count($names) > 0 ? $names[0]->getDisplayName() : '';
        $picture = $photos && count($photos) > 0 ? $photos[0]->getUrl() : '';
        
        // Debug - remove after testing
        error_log("People API - Email: $email, Name: $name, Picture: $picture");
        
        // Ensure we have required data
        if (empty($email)) {
            throw new Exception("Failed to get user email from Google People API");
        }
        if (empty($name)) {
            throw new Exception("Failed to get user name from Google People API");
        }
        
        // Generate remember token
        $remember_token = bin2hex(random_bytes(16));
        $login_method = 'google';
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, login_method FROM users WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // New Google user - insert
            $insert = $conn->prepare("INSERT INTO users (name, email, password, remember_token, profile_picture, login_method) VALUES (?, ?, '', ?, ?, ?)");
            if (!$insert) {
                throw new Exception("Database prepare failed for insert: " . $conn->error);
            }
            
            $insert->bind_param("sssss", $name, $email, $remember_token, $picture, $login_method);
            
            if (!$insert->execute()) {
                throw new Exception("Failed to create new user: " . $insert->error);
            }
            
            $user_id = $insert->insert_id;
            
        } else {
            // Existing user
            $row = $result->fetch_assoc();
            $user_id = $row['id'];
            $existing_method = $row['login_method'];
            
            // Update user information
            if ($existing_method !== 'google') {
                $update = $conn->prepare("UPDATE users SET remember_token = ?, profile_picture = ?, login_method = ? WHERE id = ?");
                if (!$update) {
                    throw new Exception("Database prepare failed for update: " . $conn->error);
                }
                $update->bind_param("sssi", $remember_token, $picture, $login_method, $user_id);
            } else {
                $update = $conn->prepare("UPDATE users SET remember_token = ?, profile_picture = ? WHERE id = ?");
                if (!$update) {
                    throw new Exception("Database prepare failed for update: " . $conn->error);
                }
                $update->bind_param("ssi", $remember_token, $picture, $user_id);
            }
            
            if (!$update->execute()) {
                throw new Exception("Failed to update user: " . $update->error);
            }
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_picture'] = $picture;
        $_SESSION['google_logged_in'] = true;
        $_SESSION['login_success'] = 'Welcome, ' . htmlspecialchars($name) . '!';
        // ✅ Assign referral code if user came from a referral link
        require_once '../includes/referral_tracker.php';
        assignReferralToUser($conn, $user_id);
        
        // Set remember token cookie
        setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), "/", "", false, true);
        
        header("Location: otherpage/index-page-1-A-B-C-D-E.php");
        exit;
        
    } catch (Exception $e) {
        error_log("Google OAuth callback error: " . $e->getMessage());
        $_SESSION['login_needed'] = 'Login failed: ' . $e->getMessage();
        header("Location: otherpage/index-page-1-A-B-C-D-E.php");
        exit;
    }
    
} else {
    $_SESSION['login_needed'] = 'Please sign in first.';
    header("Location: otherpage/index-page-1-A-B-C-D-E.php");
    exit;
}
?>