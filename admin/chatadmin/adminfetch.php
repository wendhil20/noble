<?php 
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['admin', 'superadmin']); // allow only admin and superadmin


$user_id = intval($_POST['user_id']);
$admin_id = intval($_POST['admin_id']);

// Get messages between user and admin
$stmt = $conn->prepare("
    SELECT m.*, u.name as sender_name 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) 
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
$stmt->execute();
$messages = $stmt->get_result();

if ($messages->num_rows > 0):
    while ($message = $messages->fetch_assoc()):
        $isAdmin = ($message['sender_id'] == $admin_id);
        $messageTime = date('M j, g:i A', strtotime($message['created_at']));
        $senderName = $isAdmin ? 'Admin' : htmlspecialchars($message['sender_name']);
        $senderInitial = strtoupper(substr($senderName, 0, 1));
        
        if ($isAdmin): 
            // Admin message - right side (sent)
?>
            <div class="flex justify-end mb-4 message-item">
                <div class="flex items-end space-x-2 max-w-xs lg:max-w-md">
                    <div class="flex flex-col">
                        <div class="bg-orange-500 text-white px-4 py-2 rounded-2xl rounded-br-md shadow-sm">
                            <p class="text-sm break-words"><?= htmlspecialchars($message['message']) ?></p>
                        </div>
                        <div class="text-xs text-gray-500 mt-1 text-right">
                            <span class="font-medium">Admin</span> • <?= $messageTime ?>
                        </div>
                    </div>
                    <div class="w-8 h-8 bg-orange-600 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                        <?= $senderInitial ?>
                    </div>
                </div>
            </div>
<?php 
        else: 
            // User message - left side (received)
?>
            <div class="flex justify-start mb-4 message-item">
                <div class="flex items-end space-x-2 max-w-xs lg:max-w-md">
                    <div class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                        <?= $senderInitial ?>
                    </div>
                    <div class="flex flex-col">
                        <div class="bg-gray-200 text-gray-800 px-4 py-2 rounded-2xl rounded-bl-md shadow-sm">
                            <p class="text-sm break-words"><?= htmlspecialchars($message['message']) ?></p>
                        </div>
                        <div class="text-xs text-gray-500 mt-1 text-left">
                            <span class="font-medium"><?= $senderName ?></span> • <?= $messageTime ?>
                        </div>
                    </div>
                </div>
            </div>
<?php 
        endif;
    endwhile;
else: 
?>
    <div class="text-center py-8 text-gray-500">
        <div class="w-16 h-16 bg-gray-300 rounded-full mx-auto mb-4 flex items-center justify-center">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
        </div>
        <p>No messages yet</p>
        <p class="text-sm mt-1">Start the conversation by sending a message</p>
    </div>
<?php 
endif; 
?>

<style>
.message-item {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { 
        opacity: 0; 
        transform: translateY(10px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0); 
    }
}

/* Smooth message bubbles */
.message-item div[class*="bg-orange-500"] {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
}

.message-item div[class*="bg-gray-200"] {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

/* Better responsive design */
@media (max-width: 640px) {
    .message-item .max-w-xs {
        max-width: 250px;
    }
}
</style>