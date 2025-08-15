<?php
// chat_fetch_sales
session_name("nobleuser");
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    include '../../connection/connect.php';
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access");
    }
    
    $currentUserId = $_SESSION['user_id'];
    
    // Fetch sales representatives with unread message counts
    $sql = "
        SELECT 
            n.id, 
            n.sales_id, 
            n.fullname, 
            n.email,
            COALESCE(unread.unread_count, 0) as unread_count,
            last_msg.message as last_message,
            last_msg.created_at as last_message_time
        FROM nobleaccount n
        LEFT JOIN (
            SELECT 
                sender_noble_id,
                COUNT(*) as unread_count
            FROM chat_messages 
            WHERE receiver_user_id = ? AND is_read = 0
            GROUP BY sender_noble_id
        ) unread ON n.id = unread.sender_noble_id
        LEFT JOIN (
            SELECT 
                CASE 
                    WHEN sender_user_id = ? THEN receiver_noble_id 
                    ELSE sender_noble_id 
                END as other_user_id,
                message,
                created_at,
                ROW_NUMBER() OVER (
                    PARTITION BY CASE 
                        WHEN sender_user_id = ? THEN receiver_noble_id 
                        ELSE sender_noble_id 
                    END 
                    ORDER BY created_at DESC
                ) as rn
            FROM chat_messages
            WHERE sender_user_id = ? OR receiver_user_id = ?
        ) last_msg ON n.id = last_msg.other_user_id AND last_msg.rn = 1
        WHERE n.lvl = 'sales' 
        AND n.status = 'active' 
        AND n.sales_id IS NOT NULL
        ORDER BY 
            unread.unread_count DESC, 
            last_msg.created_at DESC,
            n.fullname ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiii", $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception($conn->error);
    }
    
    $sales = [];
    while ($row = $result->fetch_assoc()) {
        $sales[] = [
            'id' => $row['id'],              
            'sales_id' => $row['sales_id'],  
            'fullname' => $row['fullname'],
            'email' => $row['email'],
            'unread_count' => (int)$row['unread_count'],
            'last_message' => $row['last_message'],
            'last_message_time' => $row['last_message_time']
        ];
    }
    
    echo json_encode($sales);
    
} catch (Exception $e) {
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage()
    ]);
}
?>