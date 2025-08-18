<?php
include '../../connection/connect.php';
include 'auth.php';

// ✅ Final check if logged in (either normal or Google)
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login/Google callback
    header('Location: google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$admin_id = 1;

$stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
$stmt->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()):
    $isUser = $row['sender_id'] == $user_id;
    $align = $isUser ? 'justify-end' : 'justify-start';
    $bgColor = $isUser ? 'bg-orange-100' : 'bg-gray-200';
    $who = $isUser ? 'You' : 'Admin';
?>
    <div class="flex <?= $align ?> mb-2 px-2">
        <div class="<?= $bgColor ?> p-3 rounded-lg max-w-[75%] w-fit break-words">
            <p class="text-sm whitespace-pre-line"><strong><?= $who ?>:</strong> <?= htmlspecialchars($row['message']) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= $row['created_at'] ?></p>
        </div>
    </div>
<?php endwhile; ?>


