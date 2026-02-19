<?php
session_name("nobleadmin");
session_start();
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);
// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
  // Redirect to login page
  header("Location: ../../loginpage/index.php");
  exit();
}



// Get admin info
$adminId = $_SESSION['noble_user'];
$stmt = $conn->prepare("SELECT * FROM nobleaccount WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$adminResult = $stmt->get_result();
$admin = $adminResult->fetch_assoc();


?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Chat - Customer Support</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  <style>
    .chat-container {
      height: calc(100vh - 120px);
    }

    .messages-container {
      height: calc(100% - 80px);
    }

    .conversation-item:hover {
      background-color: #f3f4f6;
    }

    .active-conversation {
      background-color: #cccac5ff !important;
      color: white;
    }

    .message-bubble {
      max-width: 75%;
      word-wrap: break-word;
    }

    .online-indicator {
      width: 10px;
      height: 10px;
      background-color: #10b981;
      border-radius: 50%;
      display: inline-block;
      margin-right: 8px;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.5;
      }
    }

    .unread-badge {
      background-color: #ef4444;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
    }

    .typing-indicator {
      display: none;
      padding: 8px 12px;
      background-color: #e5e7eb;
      border-radius: 18px;
      margin: 4px 0;
      align-self: flex-start;
    }

    .typing-dots {
      display: inline-flex;
      gap: 2px;
    }

    .typing-dots span {
      width: 6px;
      height: 6px;
      background-color: #6b7280;
      border-radius: 50%;
      animation: typing 1.4s infinite ease-in-out;
    }

    .typing-dots span:nth-child(2) {
      animation-delay: 0.2s;
    }

    .typing-dots span:nth-child(3) {
      animation-delay: 0.4s;
    }

    @keyframes typing {

      0%,
      80%,
      100% {
        opacity: 0.3;
      }

      40% {
        opacity: 1;
      }
    }

    /* Add these to your existing <style> section */

@media (max-width: 1024px) {
  .chat-container {
    height: calc(100vh - 140px);
  }
}

@media (max-width: 768px) {
  .chat-container {
    height: calc(100vh - 120px);
  }
}

/* Mobile-first conversation list */
@media (max-width: 1023px) {
  .conversation-item {
    padding: 12px 16px;
  }
  
  .conversation-item .text-sm {
    font-size: 0.75rem;
  }
}

/* Message bubbles responsive */
.message-bubble {
  max-width: 85%;
  word-wrap: break-word;
  overflow-wrap: anywhere;
  hyphens: auto;
}

@media (max-width: 640px) {
  .message-bubble {
    max-width: 90%;
    font-size: 0.875rem;
  }
}

/* Responsive unread badge */
.unread-badge {
  min-width: 18px;
  height: 18px;
  font-size: 11px;
}

@media (min-width: 640px) {
  .unread-badge {
    min-width: 20px;
    height: 20px;
    font-size: 12px;
  }
}

/* Ensure proper flex behavior on mobile */
@media (max-width: 1023px) {
  .chat-container > div:first-child {
    flex-shrink: 0;
  }
  
  .chat-container > div:last-child {
    flex: 1;
    min-height: 0;
  }
}
  </style>
</head>

<body class="">
  <?php include '../navbar/top.php'; ?>
  <!-- Header -->
<!-- Replace your main chat interface with this -->
<div class="flex flex-col lg:flex-row chat-container">
  <!-- Conversations List -->
  <div class="w-full lg:w-80 bg-white border-b lg:border-b-0 lg:border-r border-gray-200 flex flex-col h-64 lg:h-auto">
    <!-- Search -->
    <div class="p-3 sm:p-4 border-b">
      <div class="relative">
        <input
          type="text"
          placeholder="Search..."
          class="w-full pl-8 sm:pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          id="searchInput"
          onkeyup="filterConversations()">
        <i class="fas fa-search absolute left-2 sm:left-3 top-3 text-gray-400 text-xs sm:text-sm"></i>
      </div>
    </div>

    <!-- Conversations -->
    <div class="flex-1 overflow-y-auto" id="conversationsList">
      <div class="p-4 text-center text-gray-500">
        <i class="fas fa-spinner fa-spin text-xl sm:text-2xl mb-2"></i>
        <div class="text-sm">Loading conversations...</div>
      </div>
    </div>
  </div>

  <!-- Chat Area -->
  <div class="flex-1 flex flex-col min-h-0">
    <!-- Chat Header -->
    <div class="bg-white border-b border-gray-200 px-3 sm:px-6 py-3 sm:py-4" id="chatHeader">
      <div class="flex items-center justify-between">
        <div class="text-center text-gray-500 w-full">
          <i class="fas fa-comments text-2xl sm:text-4xl mb-2 text-gray-300"></i>
          <h3 class="text-base sm:text-lg font-medium text-gray-700">Select a conversation</h3>
          <p class="text-xs sm:text-sm text-gray-500">Choose a customer from the left to start chatting</p>
        </div>
      </div>
    </div>

    <!-- Messages -->
    <div class="flex-1 overflow-y-auto bg-gray-50 p-3 sm:p-4 min-h-0" id="messagesContainer">
      <!-- Messages will be loaded here -->
    </div>

    <!-- Message Input -->
    <div class="bg-white border-t border-gray-200 p-3 sm:p-4" id="messageInput" style="display: none;">
      <div class="flex space-x-2 sm:space-x-3">
        <input
          type="text"
          placeholder="Type your message..."
          class="flex-1 px-3 sm:px-4 py-2 sm:py-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          id="messageText"
          onkeypress="handleMessageKeyPress(event)">
        <button
          onclick="sendMessage()"
          class="px-4 sm:px-6 py-2 sm:py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          id="sendButton">
          <i class="fas fa-paper-plane text-sm"></i>
        </button>
      </div>
    </div>
  </div>
</div>

  <script>
    let currentUserId = null;
    let conversations = [];
    let messages = [];
    let refreshInterval;
    let statsInterval;
    let messageRefreshInterval;

    // Initialize the chat interface
    document.addEventListener('DOMContentLoaded', function() {
      loadConversations();
      loadStats();

      // Set up auto-refresh
      refreshInterval = setInterval(loadConversations, 5000); // Refresh every 5 seconds
      statsInterval = setInterval(loadStats, 10000); // Refresh stats every 10 seconds
    });

    // Load all conversations
    function loadConversations() {
      fetch('admin_fetch_users.php')
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.text();
        })
        .then(text => {
          try {
            const data = JSON.parse(text);
            if (data.error) {
              console.error('Error:', data.error);
              showErrorInConversations('Error: ' + data.error);
              return;
            }

            conversations = data;
            displayConversations(data);
          } catch (e) {
            console.error('Invalid JSON response from admin_fetch_users.php:', text);
            showErrorInConversations('Failed to load conversations');
          }
        })
        .catch(error => {
          console.error('Error loading conversations:', error);
          showErrorInConversations('Network error loading conversations');
        });
    }

    // Show error in conversations list
    function showErrorInConversations(message) {
      const container = document.getElementById('conversationsList');
      if (container) {
        container.innerHTML = `
                    <div class="p-4 text-center text-red-500">
                        <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                        <div>${message}</div>
                        <button onclick="loadConversations()" class="mt-2 text-sm bg-red-100 hover:bg-red-200 px-3 py-1 rounded">
                            Try Again
                        </button>
                    </div>
                `;
      }
    }

    // Display conversations in the sidebar
    function displayConversations(convos) {
      const container = document.getElementById('conversationsList');

      if (convos.length === 0) {
        container.innerHTML = `
                    <div class="p-4 text-center text-gray-500">
                        <i class="fas fa-inbox text-3xl mb-2"></i>
                        <div>No conversations yet</div>
                    </div>
                `;
        return;
      }

      container.innerHTML = convos.map(convo => `
                <div class="conversation-item p-4 border-b cursor-pointer ${currentUserId == convo.user_id ? 'active-conversation' : ''}" 
                     onclick="selectConversation(${convo.user_id})">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <h4 class="font-medium text-sm truncate">${convo.name || 'Unknown User'}</h4>
                                ${convo.unread_count > 0 ? `<div class="unread-badge">${convo.unread_count}</div>` : ''}
                            </div>
                            <p class="text-xs text-gray-600 mb-1">${convo.email || ''}</p>
                            <p class="text-sm text-gray-500 truncate">${convo.last_message || 'No messages yet'}</p>
                            <p class="text-xs text-gray-400 mt-1">${convo.time_ago}</p>
                        </div>
                    </div>
                </div>
            `).join('');
    }

    // Select a conversation
    function selectConversation(userId) {
      currentUserId = userId;

      // Update active state
      document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active-conversation');
      });

      // Find and highlight the clicked conversation
      const clickedItem = document.querySelector(`[onclick="selectConversation(${userId})"]`);
      if (clickedItem) {
        clickedItem.classList.add('active-conversation');
      }

      // Clear previous message interval kung meron
      if (messageRefreshInterval) clearInterval(messageRefreshInterval);

      // Set interval para mag auto-refresh messages bawat 2 seconds
      messageRefreshInterval = setInterval(() => {
        loadMessages(userId);
      }, 2000);

      // Mark messages as read
      markMessagesAsRead(userId);

      // Load messages
      loadMessages(userId);

      // Show message input
      document.getElementById('messageInput').style.display = 'block';

      // Update header
      const user = conversations.find(c => c.user_id == userId);
      if (user) {
        document.getElementById('chatHeader').innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium">${user.name || 'Unknown User'}</h3>
                                <p class="text-sm text-gray-500">${user.email || ''}</p>
                            </div>
                        </div>
                        <button onclick="refreshMessages()" class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                `;
      }
    }

    function loadMessages(userId) {
      fetch(`admin_getmessage.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
          if (!data.error) {
            // I-check kung may bagong message bago i-update UI
            const lastMessage = messages.length ? messages[messages.length - 1].id : null;
            const newLastMessage = data.length ? data[data.length - 1].id : null;

            if (lastMessage !== newLastMessage) {
              messages = data;
              displayMessages(data);
            }
          }
        })
        .catch(error => console.error('Error loading messages:', error));
    }

    // Display messages in the chat area
    function displayMessages(msgs) {
      const container = document.getElementById('messagesContainer');

      if (msgs.length === 0) {
        container.innerHTML = `
                    <div class="text-center text-gray-500 mt-8">
                        <i class="fas fa-comment-alt text-3xl mb-2"></i>
                        <div>No messages yet. Start the conversation!</div>
                    </div>
                `;
        return;
      }

      container.innerHTML = msgs.map(msg => {
        const isAdmin = msg.is_admin == 1;
        return `
                    <div class="flex ${isAdmin ? 'justify-end' : 'justify-start'} mb-4">
                        <div class="message-bubble ${isAdmin ? 'bg-blue-600 text-white' : 'bg-white text-gray-800'} rounded-2xl px-4 py-2 shadow-sm">
                            <div class="text-sm">${msg.message}</div>
                            <div class="text-xs ${isAdmin ? 'text-blue-100' : 'text-gray-500'} mt-1">
                                ${msg.formatted_date}
                            </div>
                        </div>
                    </div>
                `;
      }).join('');

      // Add typing indicator
      container.innerHTML += `
             
            `;

      // Scroll to bottom
      container.scrollTop = container.scrollHeight;
    }

    // Send a message
    function sendMessage() {
      const messageText = document.getElementById('messageText').value.trim();

      if (!messageText || !currentUserId) return;

      // Disable send button
      const sendButton = document.getElementById('sendButton');
      sendButton.disabled = true;
      sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

      // Send message
      fetch('admin_sendmessage.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            receiver_user_id: currentUserId,
            message: messageText
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            document.getElementById('messageText').value = '';
            loadMessages(currentUserId);
            loadConversations(); // Refresh to update last message
          } else {
            alert('Error sending message: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error sending message:', error);
          alert('Error sending message');
        })
        .finally(() => {
          // Re-enable send button
          sendButton.disabled = false;
          sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
        });
    }

    // Handle Enter key in message input
    function handleMessageKeyPress(event) {
      if (event.key === 'Enter') {
        sendMessage();
      }
    }

    // Mark messages as read
    function markMessagesAsRead(userId) {
      fetch('admin_mark_read.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            user_id: userId
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            // Update conversation list to remove unread badge
            loadConversations();
          }
        })
        .catch(error => {
          console.error('Error marking messages as read:', error);
        });
    }

    // Load dashboard stats
    function loadStats() {
      fetch('admin_stats.php')
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.text();
        })
        .then(text => {
          try {
            const data = JSON.parse(text);
            if (data.error) {
              console.error('Error loading stats:', data.error);
              return;
            }

            const totalEl = document.getElementById('totalMessages');
            const unreadEl = document.getElementById('unreadMessages');
            const activeEl = document.getElementById('activeUsers');

            if (totalEl) totalEl.textContent = data.total_messages || '0';
            if (unreadEl) unreadEl.textContent = data.unread_messages || '0';
            if (activeEl) activeEl.textContent = data.active_users || '0';
          } catch (e) {
            console.error('Invalid JSON response from admin_stats.php:', text);
            // Set default values if stats fail
            const totalEl = document.getElementById('totalMessages');
            const unreadEl = document.getElementById('unreadMessages');
            const activeEl = document.getElementById('activeUsers');

            if (totalEl) totalEl.textContent = '0';
            if (unreadEl) unreadEl.textContent = '0';
            if (activeEl) activeEl.textContent = '0';
          }
        })
        .catch(error => {
          console.error('Error loading stats:', error);
          // Set fallback values
          const totalEl = document.getElementById('totalMessages');
          const unreadEl = document.getElementById('unreadMessages');
          const activeEl = document.getElementById('activeUsers');

          if (totalEl) totalEl.textContent = '0';
          if (unreadEl) unreadEl.textContent = '0';
          if (activeEl) activeEl.textContent = '0';
        });
    }

    // Filter conversations
    function filterConversations() {
      const searchTerm = document.getElementById('searchInput').value.toLowerCase();
      const filteredConversations = conversations.filter(convo =>
        (convo.name || '').toLowerCase().includes(searchTerm) ||
        (convo.email || '').toLowerCase().includes(searchTerm) ||
        (convo.last_message || '').toLowerCase().includes(searchTerm)
      );
      displayConversations(filteredConversations);
    }

    // Refresh messages for current conversation
    function refreshMessages() {
      if (currentUserId) {
        loadMessages(currentUserId);
      }
    }

    // Toggle user menu
    function toggleUserMenu() {
      const menu = document.getElementById('userMenu');
      menu.classList.toggle('hidden');
    }

    // Close user menu when clicking outside
    document.addEventListener('click', function(event) {
      const menu = document.getElementById('userMenu');
      const button = event.target.closest('button');

      if (!button || !button.getAttribute('onclick')?.includes('toggleUserMenu')) {
        menu.classList.add('hidden');
      }
    });

    // Cleanup intervals when page unloads
    window.addEventListener('beforeunload', function() {
      if (refreshInterval) clearInterval(refreshInterval);
      if (statsInterval) clearInterval(statsInterval);
    });
  </script>
</body>

</html>