<?php 
// chat_fetch_sales - FIXED VERSION
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
    
    // 🔥 CORRECT QUERY - Individual status for each sales rep
    $sql = "
        SELECT DISTINCT
            n.id,
            n.sales_id,
            n.fullname,
            n.email,
            COALESCE(n.is_online, 0) as is_online
        FROM nobleaccount n
        WHERE n.lvl = 'sales' 
        AND n.status = 'active' 
        AND n.sales_id IS NOT NULL
        ORDER BY n.is_online DESC, n.fullname ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception($conn->error);
    }
    
    $sales = [];
    while ($row = $result->fetch_assoc()) {
        
        // 🔥 Get unread count separately for each user
        $unreadStmt = $conn->prepare("
            SELECT COUNT(*) as unread_count 
            FROM chat_messages 
            WHERE sender_noble_id = ? AND receiver_user_id = ? AND is_read = 0
        ");
        $unreadStmt->bind_param("ii", $row['id'], $currentUserId);
        $unreadStmt->execute();
        $unreadResult = $unreadStmt->get_result();
        $unreadData = $unreadResult->fetch_assoc();
        $unreadStmt->close();
        
        // 🔥 Get last message separately for each user
        $lastMsgStmt = $conn->prepare("
            SELECT message, created_at 
            FROM chat_messages 
            WHERE (sender_user_id = ? AND receiver_noble_id = ?) 
               OR (sender_noble_id = ? AND receiver_user_id = ?)
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $lastMsgStmt->bind_param("iiii", $currentUserId, $row['id'], $row['id'], $currentUserId);
        $lastMsgStmt->execute();
        $lastMsgResult = $lastMsgStmt->get_result();
        $lastMsgData = $lastMsgResult->fetch_assoc();
        $lastMsgStmt->close();
        
        $sales[] = [
            'id' => $row['id'],                          
            'sales_id' => $row['sales_id'],              
            'fullname' => $row['fullname'],
            'email' => $row['email'],
            'is_online' => (int)$row['is_online'], // 🔥 Online status (0 or 1)
            'status_class' => ((int)$row['is_online'] == 1) ? 'bg-green-500' : 'bg-gray-400', // 🔥 CSS class
            'status_text' => ((int)$row['is_online'] == 1) ? 'Online' : 'Offline', // 🔥 Display text
            'unread_count' => (int)($unreadData['unread_count'] ?? 0),
            'last_message' => $lastMsgData['message'] ?? null,
            'last_message_time' => $lastMsgData['created_at'] ?? null
        ];
    }
    
    // 🔥 Sort by unread count DESC, then by last message time
    usort($sales, function($a, $b) {
        if ($a['unread_count'] != $b['unread_count']) {
            return $b['unread_count'] - $a['unread_count']; // Unread first
        }
        return strcmp($b['last_message_time'], $a['last_message_time']); // Latest first
    });
    
    echo json_encode($sales);
    
} catch (Exception $e) {
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage()
    ]);
}
?>