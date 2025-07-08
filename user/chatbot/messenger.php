<?php
include '../../connection/connect.php';
include 'auth.php';

$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Messenger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        #chat-box::-webkit-scrollbar {
            display: none;
        }
        #chat-box {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-orange-600">Chat with Assistant</h2>
    </div>

    <?php if ($isLoggedIn): ?>
        <?php
        $user_id = $_SESSION['user_id'];
        $admin_id = 1;

        $stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
        $stmt->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
        $stmt->execute();
        $messages = $stmt->get_result();
        ?>

        <!-- Chat Box -->
        <div id="chat-box" class="h-[300px] overflow-y-scroll border border-gray-300 rounded-md p-4 bg-gray-50 space-y-4 mb-4">
            <!-- Messages loaded via AJAX -->
        </div>

        <!-- Scroll Button -->
        <button id="scrollBtn"
                onclick="scrollToBottom()"
                class="fixed bottom-8 right-8 bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-full shadow-lg hidden z-50">
            ↓ Scroll to Bottom
        </button>

        <!-- Send Form -->
        <form id="messageForm" class="space-y-3">
            <textarea name="message" id="messageInput" placeholder="Type your message..." rows="3" required
                      class="w-full p-3 rounded-md border border-gray-300 focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none"></textarea>
            <button type="submit"
                    class="bg-orange-500 text-white px-4 py-2 rounded-md hover:bg-orange-600 transition w-full">Send</button>
        </form>

        <script>
            const chatBox = document.getElementById("chat-box");
            const scrollBtn = document.getElementById("scrollBtn");
            const messageInput = document.getElementById("messageInput");

            function scrollToBottom() {
                chatBox.scrollTop = chatBox.scrollHeight;
            }

            // Show/hide scroll button
            chatBox.addEventListener("scroll", () => {
                const nearBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 100;
                scrollBtn.classList.toggle("hidden", nearBottom);
            });

            // Load messages
            function loadMessages() {
                const prevScroll = chatBox.scrollHeight;
                $.ajax({
                    url: "fetch_messages.php",
                    method: "POST",
                    success: function (data) {
                        $("#chat-box").html(data);
                        const newScroll = chatBox.scrollHeight;
                        const nearBottom = prevScroll - chatBox.scrollTop <= chatBox.clientHeight + 100;
                        if (nearBottom || newScroll > prevScroll) scrollToBottom();
                    }
                });
            }
            setInterval(loadMessages, 1000);
            loadMessages();

            // Send message
            $("#messageForm").on("submit", function (e) {
                e.preventDefault();
                const msg = $("#messageInput").val().trim();
                const btn = $(this).find("button");

                if (!msg) return;

                btn.prop("disabled", true).text("Sending...");
                $.ajax({
                    url: "send_message.php",
                    method: "POST",
                    data: { message: msg },
                    success: function () {
                        $("#messageInput").val("");
                        scrollToBottom();
                    },
                    complete: function () {
                        btn.prop("disabled", false).text("Send");
                    }
                });
            });

            // Press enter to send
            messageInput.addEventListener("keydown", function (e) {
                if (e.key === "Enter" && !e.shiftKey) {
                    e.preventDefault();
                    document.getElementById("messageForm").dispatchEvent(new Event("submit"));
                }
            });
        </script>

    <?php else: ?>
        <!-- Not Logged In -->
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded">
            <p class="font-medium">Notice:</p>
            <p>Please log in to access the chat feature.</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
