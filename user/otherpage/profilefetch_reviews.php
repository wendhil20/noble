<?php
include '../../connection/connect.php'; // adjust path

header('Content-Type: application/json');

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
