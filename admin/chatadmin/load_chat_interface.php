<?php
include '../../connection/connect.php';

$user_id = intval($_POST['user_id']);
$admin_id = intval($_POST['admin_id']);

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "User not found";
    exit;
}
?>

<!-- Chat Header -->
<div class="bg-white border-b border-gray-200 p-4 flex items-center">
    <div class="flex items-center">
        <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center text-white font-bold">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <div class="ml-3">
            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($user['name']) ?></h3>
            <p class="text-sm text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
        </div>
    </div>
    <div class="ml-auto">
        <span class="text-sm text-gray-500">Online</span>
    </div>
</div>

<!-- Messages Container -->
<div class="flex-1 overflow-y-auto p-4 bg-gray-50 chat-scroll" id="chat-box">
    <div id="messages-container">
        <!-- Messages will be loaded here -->
    </div>
</div>

<!-- Scroll to bottom button -->
<button id="scrollBtn" class="fixed bottom-24 right-8 bg-orange-500 text-white p-3 rounded-full shadow-lg hover:bg-orange-600 transition-colors hidden">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
    </svg>
</button>

<!-- Message Input -->
<div class="bg-white border-t border-gray-200 p-4">
    <form id="messageForm" class="flex gap-2">
        <input type="hidden" id="receiver_id" value="<?= $user_id ?>">
        <input type="text" id="messageInput" placeholder="Type your message..." 
               class="flex-1 p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
        <button type="submit" class="bg-orange-500 text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition-colors font-medium">
            Send as Admin
        </button>
    </form>
</div>

<script>
// Load messages immediately when interface loads
loadMessages(<?= $user_id ?>, <?= $admin_id ?>);
</script>