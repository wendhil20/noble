<?php
include '../../connection/connect.php';

include '../role/roleaccount.php';
require_role(['admin', 'superadmin']); // allow only admin and superadmin


$admin_id = intval($_POST['admin_id']);

// Get users who sent messages to the selected admin
$stmt = $conn->prepare("
    SELECT 
        u.id, 
        u.name, 
        u.email,
        MAX(m.message) as last_message,
        MAX(m.created_at) as last_message_time,
        COUNT(m.id) as message_count,
        SUM(CASE WHEN m.receiver_id = ? THEN 1 ELSE 0 END) as messages_to_admin,
        SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count
    FROM users u 
    INNER JOIN messages m ON u.id = m.sender_id
    WHERE m.receiver_id = ?
    GROUP BY u.id, u.name, u.email
    ORDER BY last_message_time DESC
");
$stmt->bind_param("iii", $admin_id, $admin_id, $admin_id);
$stmt->execute();
$users = $stmt->get_result();

if ($users->num_rows > 0):
    while ($user = $users->fetch_assoc()):
?>
    <div class="user-item p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer" 
         data-user-id="<?= $user['id'] ?>">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center text-white text-sm font-bold mr-3">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($user['name']) ?></h3>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>
                <div class="mt-2 ml-11">
                    <p class="text-sm text-gray-500 truncate">
                        <?= $user['last_message'] ? htmlspecialchars(substr($user['last_message'], 0, 50)) . '...' : 'No messages' ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        <?= $user['last_message_time'] ? date('M j, Y g:i A', strtotime($user['last_message_time'])) : '' ?>
                    </p>
                </div>
            </div>
            <?php if ($user['unread_count'] > 0): ?>
                <div class="ml-2">
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                        <?= $user['unread_count'] ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php 
    endwhile;
else:
?>
    <div class="p-4 text-center text-gray-500">
        <div class="w-12 h-12 bg-gray-300 rounded-full mx-auto mb-3 flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
        </div>
        <p class="text-sm">No users have sent messages to this admin yet.</p>
        <p class="text-xs text-gray-400 mt-1">Messages will appear here when users contact this admin.</p>
    </div>
<?php endif; ?>