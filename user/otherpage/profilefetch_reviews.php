<?php
include '../../connection/connect.php'; // adjust path

header('Content-Type: application/json');


// ✅ Restore session from remember_token (normal account or Google)
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

        // Check if the account is Google-based (optional flag or logic)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Final check if logged in (either normal or Google)
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login/Google callback
    header('Location: ../google-callback.php');
    exit;
}

$sql = "
    SELECT f.rating, f.comment, f.created_at, 
           u.name, u.profile_picture
    FROM user_feedback f
    JOIN users u ON f.user_id = u.id
    ORDER BY RAND()
";
$result = $conn->query($sql);

$reviews = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $reviews[] = [
            'rating' => (int)$row['rating'],
            'comment' => $row['comment'],
            'created_at' => $row['created_at'],
            'name' => $row['name'],
            'profile_picture' => !empty($row['profile_picture']) ? $row['profile_picture'] : 'default-avatar.png'
        ];
    }
}

echo json_encode($reviews);
