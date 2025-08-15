<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$currentUserId = $_SESSION['user_id'];

// Get all sales reps with their unread message counts and last messages
$query = "
    SELECT 
        u.id,
        u.fullname,
        u.email,
        u.user_picture,
        COALESCE(unread.unread_count, 0) as unread_count,
        last_msg.message as last_message,
        last_msg.created_at as last_message_time
    FROM users u
    LEFT JOIN (
        SELECT 
            sender_id,
            COUNT(*) as unread_count
        FROM chat_messages 
        WHERE receiver_id = ? AND is_read = 0
        GROUP BY sender_id
    ) unread ON u.id = unread.sender_id
    LEFT JOIN (
        SELECT 
            CASE 
                WHEN sender_id = ? THEN receiver_id 
                ELSE sender_id 
            END as other_user_id,
            message,
            created_at,
            ROW_NUMBER() OVER (
                PARTITION BY CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END 
                ORDER BY created_at DESC
            ) as rn
        FROM chat_messages
        WHERE sender_id = ? OR receiver_id = ?
    ) last_msg ON u.id = last_msg.other_user_id AND last_msg.rn = 1
    WHERE u.user_type = 'sales'
    ORDER BY 
        unread.unread_count DESC, 
        last_msg.created_at DESC,
        u.fullname ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiiii", $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();

$sales = [];
while ($row = $result->fetch_assoc()) {
    $sales[] = $row;
}

echo json_encode([
    "sales" => $sales
]);

$stmt->close();
$conn->close();
?>