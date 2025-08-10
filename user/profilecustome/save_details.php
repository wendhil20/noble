<?php 
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

$notification = ""; // For success/error messages

// ✅ Restore session from remember_token (if not already logged in)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// ✅ If form is submitted, insert/update directly
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sex        = $_POST['sex'];
    $birthplace = $_POST['birthplace'];
    $birthdate  = $_POST['birthdate'];
    $occupation = $_POST['occupation'];

    // Check if record exists
    $stmt = $conn->prepare("SELECT user_id FROM user_details WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        // Update
        $sql = "UPDATE user_details 
                SET sex=?, birthplace=?, birthdate=?, occupation=?, is_verified=0 
                WHERE user_id=?";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param("ssssi", $sex, $birthplace, $birthdate, $occupation, $_SESSION['user_id']);
        $stmt2->execute();
        $stmt2->close();
        $notification = "<div class='mb-4 p-3 bg-blue-100 border-l-4 border-blue-500 text-blue-700 rounded'>
                            ✅ Profile updated. Waiting for verification.
                         </div>";
    } else {
        // Insert
        $sql = "INSERT INTO user_details (user_id, sex, birthplace, birthdate, occupation, is_verified) 
                VALUES (?, ?, ?, ?, ?, 0)";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param("issss", $_SESSION['user_id'], $sex, $birthplace, $birthdate, $occupation);
        $stmt2->execute();
        $stmt2->close();
        $notification = "<div class='mb-4 p-3 bg-green-100 border-l-4 border-green-500 text-green-700 rounded'>
                            🎉 Profile saved. Waiting for verification.
                         </div>";
    }
    $stmt->close();
}

// ✅ Fetch existing details after update/insert
$detail = [
    'sex' => '',
    'birthplace' => '',
    'birthdate' => '',
    'occupation' => '',
    'is_verified' => 0
];
$stmt = $conn->prepare("SELECT * FROM user_details WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $detail = $res->fetch_assoc();
}
$stmt->close();
?>
