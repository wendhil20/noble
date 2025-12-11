<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get all customize requests
$query = "
    SELECT 
        ccr.id,
        ccr.product_id,
        ccr.full_name,
        ccr.email,
        ccr.phone,
        ccr.custom_type,
        ccr.status,
        ccr.created_at,
        p.product_name,
        p.codename
    FROM custom_quote_requests ccr
    LEFT JOIN products p ON ccr.product_id = p.id
    ORDER BY ccr.created_at DESC
";

$result = $conn->query($query);
$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

// Get selected request ID
$selectedId = isset($_GET['id']) ? intval($_GET['id']) : null;

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE custom_quote_requests SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Get selected request details
$selectedRequest = null;
if ($selectedId) {
    $stmt = $conn->prepare("
        SELECT 
            ccr.*,
            p.product_name,
            p.codename
        FROM custom_quote_requests ccr
        LEFT JOIN products p ON ccr.product_id = p.id
        WHERE ccr.id = ?
    ");
    $stmt->bind_param("i", $selectedId);
    $stmt->execute();
    $result = $stmt->get_result();
    $selectedRequest = $result->fetch_assoc();
    $stmt->close();
}

// Separate requests by status
$pending = array_filter($requests, fn($r) => $r['status'] === 'pending');
$quoted = array_filter($requests, fn($r) => $r['status'] === 'quoted');
$completed = array_filter($requests, fn($r) => $r['status'] === 'completed');
$total = count($requests);
$pendingCount = count($pending);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize Requests - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../navbar/top.php'; ?>
    
    <div class="container mx-auto p-4 lg:p-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-envelope mr-2 text-orange-500"></i>
                Customize Requests
            </h1>
            <p class="text-gray-600">Manage customer customization requests and quotes</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg p-6 shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Total Requests</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $total ?></p>
                    </div>
                    <i class="fas fa-inbox text-4xl text-blue-500 opacity-20"></i>
                </div>
            </div>

            <div class="bg-white rounded-lg p-6 shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Pending</p>
                        <p class="text-3xl font-bold text-orange-600"><?= $pendingCount ?></p>
                    </div>
                    <i class="fas fa-clock text-4xl text-orange-500 opacity-20"></i>
                </div>
            </div>

            <div class="bg-white rounded-lg p-6 shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Quoted</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= count($quoted) ?></p>
                    </div>
                    <i class="fas fa-file-invoice text-4xl text-yellow-500 opacity-20"></i>
                </div>
            </div>

            <div class="bg-white rounded-lg p-6 shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Completed</p>
                        <p class="text-3xl font-bold text-green-600"><?= count($completed) ?></p>
                    </div>
                    <i class="fas fa-check-circle text-4xl text-green-500 opacity-20"></i>
                </div>
            </div>
        </div>

        <!-- Main Layout -->
        <div class="flex gap-6 h-[calc(100vh-500px)] relative">
            
            <!-- TOGGLE SIDEBAR BUTTON -->
            <button 
                onclick="toggleSidebar()" 
                id="toggleBtn"
                class="fixed left-4 bottom-4 z-40 bg-orange-500 hover:bg-orange-600 text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg transition">
                <i class="fas fa-bars text-xl"></i>
                <?php if ($pendingCount > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
                        <?= $pendingCount ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- HIDDEN SIDEBAR -->
            <div id="sidebar" class="fixed left-0 top-0 w-96 h-screen bg-white shadow-2xl transform -translate-x-full transition-transform duration-300 z-30 flex flex-col">
                
                <!-- Close Button -->
                <button onclick="toggleSidebar()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-2xl z-50">
                    <i class="fas fa-times"></i>
                </button>

                <!-- Sidebar Header -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 border-b">
                    <h2 class="font-bold text-lg flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        Pending Requests
                    </h2>
                    <p class="text-sm text-blue-100 mt-1"><?= $pendingCount ?> waiting</p>
                </div>

                <!-- Sidebar Content -->
                <div class="overflow-y-auto flex-1 p-4">
                    <?php if (empty($pending)): ?>
                        <div class="p-8 text-center text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-3 block opacity-50"></i>
                            <p class="text-sm">No pending requests</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending as $req): ?>
                            <div class="p-3 mb-2 border border-orange-200 rounded-lg group hover:shadow-md transition">
                                <a href="?id=<?= $req['id'] ?>" onclick="toggleSidebar(); return true;" class="block group-hover:text-orange-600 transition">
                                    <p class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars($req['full_name']) ?></p>
                                    <p class="text-xs text-gray-600 truncate"><?= htmlspecialchars($req['email']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1 truncate"><?= htmlspecialchars($req['product_name'] ?? 'N/A') ?></p>
                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-xs bg-orange-200 text-orange-800 px-2 py-0.5 rounded">
                                            <?= ucfirst(str_replace('_', ' ', $req['custom_type'])) ?>
                                        </span>
                                        <span class="text-xs text-gray-500"><?= date('M d', strtotime($req['created_at'])) ?></span>
                                    </div>
                                </a>
                                <a href="#" onclick="updateStatusToQuoted(<?= $req['id'] ?>); return false;" class="w-full mt-2 bg-green-500 hover:bg-green-600 text-white text-xs font-semibold py-1.5 px-2 rounded transition flex items-center justify-center block">
                                    <i class="fas fa-reply mr-1"></i>Respond
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar Overlay -->
            <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-20" onclick="toggleSidebar()"></div>

            <!-- CENTER - QUOTED LIST OR REPLY FORM -->
            <div class="flex-1 bg-white rounded-lg shadow overflow-hidden flex flex-col">
                <?php if ($selectedRequest && isset($_GET['reply'])): ?>
                    <!-- REPLY FORM VIEW -->
                    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 border-b flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Reply to <?= htmlspecialchars($selectedRequest['full_name']) ?>
                            </h2>
                            <p class="text-green-100 text-sm mt-1"><?= htmlspecialchars($selectedRequest['email']) ?></p>
                        </div>
                        <a href="?quoted=1" class="text-green-100 hover:text-white text-lg transition">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>

                    <div class="overflow-y-auto flex-1 p-6">
                        <form id="replyForm" class="space-y-6">
                            <input type="hidden" name="request_id" id="request_id" value="<?= $selectedRequest['id'] ?>">
                            <input type="hidden" id="reply_email" value="<?= htmlspecialchars($selectedRequest['email']) ?>">
                            <input type="hidden" id="reply_name" value="<?= htmlspecialchars($selectedRequest['full_name']) ?>">
                            
                            <!-- Customer Info Preview -->
                            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                <p class="text-sm font-semibold text-gray-700 mb-2">Customer:</p>
                                <p class="font-bold text-gray-900"><?= htmlspecialchars($selectedRequest['full_name']) ?></p>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($selectedRequest['email']) ?></p>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($selectedRequest['phone']) ?></p>
                            </div>

                            <!-- Product Info Preview -->
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <p class="text-sm font-semibold text-gray-700 mb-2">Product:</p>
                                <p class="font-bold text-gray-900"><?= htmlspecialchars($selectedRequest['product_name'] ?? 'N/A') ?></p>
                                <span class="inline-block mt-2 text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded">
                                    <?= ucfirst(str_replace('_', ' ', $selectedRequest['custom_type'])) ?>
                                </span>
                            </div>

                            <!-- Reply Message -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Your Reply</label>
                                <textarea 
                                    name="reply_message" 
                                    placeholder="Type your message, quote details, or response here..."
                                    rows="10"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-green-500 resize-none text-sm"
                                    required></textarea>
                            </div>

                            <div id="replyStatus" class="hidden p-3 rounded-lg text-sm"></div>

                            <div class="flex gap-2">
                                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-lg transition flex items-center justify-center">
                                    <i class="fas fa-paper-plane mr-2"></i>Send Reply
                                </button>
                                <a href="?quoted=1" class="px-6 bg-gray-300 hover:bg-gray-400 text-gray-900 font-semibold py-3 rounded-lg transition">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                <?php else: ?>
                    <!-- QUOTED REQUESTS LIST -->
                    <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white p-6 border-b">
                        <h2 class="font-bold text-lg flex items-center">
                            <i class="fas fa-file-invoice mr-2"></i>
                            Quoted Requests
                        </h2>
                        <p class="text-sm text-yellow-100 mt-1"><?= count($quoted) ?> awaiting follow-up</p>
                    </div>

                    <div class="overflow-y-auto flex-1 p-6">
                        <?php if (empty($quoted)): ?>
                            <div class="flex items-center justify-center h-full text-gray-400">
                                <div class="text-center">
                                    <i class="fas fa-inbox text-6xl mb-4 opacity-20"></i>
                                    <p class="text-lg">No quoted requests yet</p>
                                    <p class="text-sm mt-2">Click "Respond" on pending requests to move them here</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($quoted as $req): ?>
                                    <a href="?quoted=1&id=<?= $req['id'] ?>&reply=1" class="p-4 border-2 border-yellow-200 rounded-lg hover:border-yellow-400 hover:shadow-lg transition bg-yellow-50">
                                        <div class="flex items-start justify-between mb-3">
                                            <div>
                                                <p class="font-bold text-gray-900"><?= htmlspecialchars($req['full_name']) ?></p>
                                                <p class="text-xs text-gray-600"><?= htmlspecialchars($req['email']) ?></p>
                                            </div>
                                            <span class="text-xs bg-yellow-200 text-yellow-800 px-2 py-1 rounded font-semibold">QUOTING</span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($req['product_name'] ?? 'N/A') ?></p>
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-gray-500"><?= date('M d, Y', strtotime($req['created_at'])) ?></span>
                                            <span class="bg-green-500 text-white px-3 py-1 rounded font-semibold flex items-center">
                                                <i class="fas fa-reply mr-1"></i>Reply
                                            </span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT - TO DO / ACTIONS -->
            <div class="flex-1 bg-white rounded-lg shadow overflow-hidden flex flex-col">
                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4">
                    <h2 class="font-bold text-lg flex items-center">
                        <i class="fas fa-tasks mr-2"></i>
                        To Do
                    </h2>
                    <p class="text-sm text-green-100 mt-1">Action items</p>
                </div>

                <div class="overflow-y-auto flex-1 p-4">
                    <?php if ($selectedRequest && isset($_GET['reply'])): ?>
                        <div class="space-y-3">
                            <div class="bg-blue-50 p-3 rounded-lg border-l-4 border-l-blue-500">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" class="todo-checkbox mt-1 w-4 h-4 text-blue-600 rounded" data-todo-id="1">
                                    <span class="ml-3">
                                        <p class="font-semibold text-sm text-gray-900">Review Request</p>
                                        <p class="text-xs text-gray-600 mt-1">Check all specs and requirements</p>
                                    </span>
                                </label>
                            </div>

                            <div class="bg-yellow-50 p-3 rounded-lg border-l-4 border-l-yellow-500">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" class="todo-checkbox mt-1 w-4 h-4 text-yellow-600 rounded" data-todo-id="2">
                                    <span class="ml-3">
                                        <p class="font-semibold text-sm text-gray-900">Send Quotation</p>
                                        <p class="text-xs text-gray-600 mt-1">Prepare and send price quote</p>
                                    </span>
                                </label>
                            </div>

                            <div class="bg-purple-50 p-3 rounded-lg border-l-4 border-l-purple-500">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" class="todo-checkbox mt-1 w-4 h-4 text-purple-600 rounded" data-todo-id="3">
                                    <span class="ml-3">
                                        <p class="font-semibold text-sm text-gray-900">Follow Up</p>
                                        <p class="text-xs text-gray-600 mt-1">Check customer response</p>
                                    </span>
                                </label>
                            </div>

                            <div class="bg-green-50 p-3 rounded-lg border-l-4 border-l-green-500">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" class="todo-checkbox mt-1 w-4 h-4 text-green-600 rounded" data-todo-id="4">
                                    <span class="ml-3">
                                        <p class="font-semibold text-sm text-gray-900">Mark Completed</p>
                                        <p class="text-xs text-gray-600 mt-1">Order finished and delivered</p>
                                    </span>
                                </label>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center justify-center h-full text-gray-400">
                            <div class="text-center">
                                <i class="fas fa-tasks text-4xl mb-3 opacity-20"></i>
                                <p class="text-xs">Select a request to see tasks</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let sidebarOpen = false;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggleBtn = document.getElementById('toggleBtn');
            
            sidebarOpen = !sidebarOpen;
            
            if (sidebarOpen) {
                // Open sidebar
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                toggleBtn.style.display = 'none'; // Hide burger button
            } else {
                // Close sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                toggleBtn.style.display = 'flex'; // Show burger button
            }
        }

        function updateStatusToQuoted(requestId) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('id', requestId);
            formData.append('status', 'quoted');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(response => {
                window.location.href = '?quoted=1&id=' + requestId + '&reply=1';
            }).catch(error => {
                console.error('Error:', error);
                window.location.href = '?quoted=1&id=' + requestId + '&reply=1';
            });
        }

        // Handle Todo Checkboxes with localStorage
        const STORAGE_KEY = 'requestTodos_';
        
        function loadTodoStates() {
            const selectedRequestId = new URLSearchParams(window.location.search).get('id');
            if (!selectedRequestId) return;
            
            const storageKey = STORAGE_KEY + selectedRequestId;
            const savedStates = JSON.parse(localStorage.getItem(storageKey) || '{}');
            
            document.querySelectorAll('.todo-checkbox').forEach(checkbox => {
                const todoId = checkbox.getAttribute('data-todo-id');
                if (savedStates[todoId]) {
                    checkbox.checked = true;
                }
            });
        }

        function initTodoListeners() {
            const selectedRequestId = new URLSearchParams(window.location.search).get('id');
            if (!selectedRequestId) return;
            
            document.querySelectorAll('.todo-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    const storageKey = STORAGE_KEY + selectedRequestId;
                    const todoId = checkbox.getAttribute('data-todo-id');
                    const states = JSON.parse(localStorage.getItem(storageKey) || '{}');
                    states[todoId] = checkbox.checked;
                    localStorage.setItem(storageKey, JSON.stringify(states));
                });
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadTodoStates();
            initTodoListeners();
        });

        // Handle reply form submission
        const replyForm = document.getElementById('replyForm');
        if (replyForm) {
            replyForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const form = e.target;
                const requestId = document.getElementById('request_id').value;
                const email = document.getElementById('reply_email').value;
                const fullName = document.getElementById('reply_name').value;
                const message = form.querySelector('textarea[name="reply_message"]').value;
                const status = document.getElementById('replyStatus');
                
                try {
                    const response = await fetch('main-send-reply-page-1-A.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            request_id: requestId,
                            email: email,
                            full_name: fullName,
                            message: message
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        status.classList.remove('hidden');
                        status.className = 'p-3 rounded-lg text-sm bg-green-100 text-green-800 border border-green-300';
                        status.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + (data.message || 'Reply sent successfully!');
                        form.reset();
                        
                        setTimeout(() => {
                            window.location.href = '?quoted=1';
                        }, 2000);
                    } else {
                        throw new Error(data.error || data.message || 'Unknown error');
                    }
                } catch (error) {
                    status.classList.remove('hidden');
                    status.className = 'p-3 rounded-lg text-sm bg-red-100 text-red-800 border border-red-300';
                    status.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>Error: ' + error.message;
                }
            });
        }
    </script>
</body>
</html>