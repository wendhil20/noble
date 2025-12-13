<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get all customize requests with ALL columns
$query = "
    SELECT 
        ccr.id,
        ccr.product_id,
        ccr.user_id,
        ccr.custom_type,
        ccr.specifications,
        ccr.full_name,
        ccr.email,
        ccr.phone,
        ccr.message,
        ccr.selected_color,
        ccr.selected_variant,
        ccr.agree_terms,
        ccr.status,
        ccr.created_at,
        ccr.updated_at,
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
        $stmt = $conn->prepare("UPDATE custom_quote_requests SET status = ?, updated_at = NOW() WHERE id = ?");
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
            ccr.id,
            ccr.product_id,
            ccr.user_id,
            ccr.custom_type,
            ccr.specifications,
            ccr.full_name,
            ccr.email,
            ccr.phone,
            ccr.message,
            ccr.selected_color,
            ccr.selected_variant,
            ccr.agree_terms,
            ccr.status,
            ccr.created_at,
            ccr.updated_at,
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
            <div class="bg-white rounded-lg p-6 shadow hover:shadow-lg transition cursor-pointer" onclick="showDashboard('all')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Total Requests</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $total ?></p>
                    </div>
                    <i class="fas fa-inbox text-4xl text-blue-500 opacity-20"></i>
                </div>
            </div>

            <div class="bg-white rounded-lg p-6 shadow hover:shadow-lg transition cursor-pointer" onclick="showDashboard('pending')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Pending</p>
                        <p class="text-3xl font-bold text-orange-600"><?= $pendingCount ?></p>
                    </div>
                    <i class="fas fa-clock text-4xl text-orange-500 opacity-20"></i>
                </div>
            </div>

            <div class="bg-white rounded-lg p-6 shadow hover:shadow-lg transition cursor-pointer" onclick="showDashboard('quoted')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Quoted</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= count($quoted) ?></p>
                    </div>
                    <i class="fas fa-file-invoice text-4xl text-yellow-500 opacity-20"></i>
                </div>
            </div>

            <div class="bg-white rounded-lg p-6 shadow hover:shadow-lg transition cursor-pointer" onclick="showDashboard('completed')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Completed</p>
                        <p class="text-3xl font-bold text-green-600"><?= count($completed) ?></p>
                    </div>
                    <i class="fas fa-check-circle text-4xl text-green-500 opacity-20"></i>
                </div>
            </div>
        </div>

        <!-- Dashboard Modal -->
        <div id="dashboardModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 flex justify-between items-center">
                    <h2 class="text-2xl font-bold" id="dashboardTitle">Dashboard</h2>
                    <button onclick="closeDashboard()" class="text-blue-100 hover:text-white text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="overflow-y-auto flex-1 p-6">
                    <div id="dashboardContent"></div>
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
                    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-3 border-b flex justify-between items-center">
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

                    <div class="overflow-y-auto flex-1 p-4">
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

                            <!-- Customer Request Details -->
                            <?php if (!empty($selectedRequest['specifications']) || !empty($selectedRequest['selected_color']) || !empty($selectedRequest['selected_variant'])): ?>
                            <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                                <p class="text-sm font-semibold text-gray-700 mb-3">Request Details:</p>
                                <?php if (!empty($selectedRequest['selected_color'])): ?>
                                    <div class="mb-2">
                                        <span class="text-xs font-semibold text-gray-600">Color:</span>
                                        <p class="text-sm text-gray-900"><?= htmlspecialchars($selectedRequest['selected_color']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($selectedRequest['selected_variant'])): ?>
                                    <div class="mb-2">
                                        <span class="text-xs font-semibold text-gray-600">Variant:</span>
                                        <p class="text-sm text-gray-900"><?= htmlspecialchars($selectedRequest['selected_variant']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($selectedRequest['specifications'])): ?>
                                    <div>
                                        <span class="text-xs font-semibold text-gray-600">Specifications:</span>
                                        <p class="text-sm text-gray-900"><?= nl2br(htmlspecialchars($selectedRequest['specifications'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Original Message -->
                            <?php if (!empty($selectedRequest['message'])): ?>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-sm font-semibold text-gray-700 mb-2">Customer's Message:</p>
                                <p class="text-sm text-gray-900"><?= nl2br(htmlspecialchars($selectedRequest['message'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Reply Message -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Your Reply</label>
                                <textarea 
                                    name="reply_message" 
                                    placeholder="Type your message, quote details, or response here..."
                                    rows="8"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-green-500 resize-none text-sm"
                                    required></textarea>
                            </div>

                            <!-- File Upload Section -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Attach Files (Optional)</label>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-green-500 transition cursor-pointer bg-gray-50" onclick="document.getElementById('fileInput').click()">
                                    <input 
                                        type="file" 
                                        id="fileInput" 
                                        name="reply_files[]" 
                                        multiple 
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip"
                                        class="hidden">
                                    <div id="uploadPrompt">
                                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2 block"></i>
                                        <p class="font-semibold text-gray-700">Click to upload files</p>
                                        <p class="text-xs text-gray-600 mt-1">or drag and drop</p>
                                        <p class="text-xs text-gray-500 mt-2">Allowed: PDF, Documents, Images, Archives (Max 10MB each)</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Uploaded Files List -->
                            <div id="filesList" class="hidden space-y-2">
                                <p class="text-sm font-semibold text-gray-700">Attached Files:</p>
                                <div id="filesContainer" class="space-y-2"></div>
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
                            <div class=" p-3 rounded-lg ">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" class="todo-checkbox mt-1 w-4 h-4 text-blue-600 rounded" data-todo-id="1">
                                    <span class="ml-3">
                                        <p class="font-semibold text-sm text-gray-900">Review Request</p>
                                        <p class="text-xs text-gray-600 mt-1">Check all specs and requirements</p>
                                    </span>
                                </label>
                            </div>

                            <div class=" p-3 rounded-lg ">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" class="todo-checkbox mt-1 w-4 h-4 text-yellow-600 rounded" data-todo-id="2">
                                    <span class="ml-3">
                                        <p class="font-semibold text-sm text-gray-900">Send Quotation</p>
                                        <p class="text-xs text-gray-600 mt-1">Prepare and send price quote</p>
                                    </span>
                                </label>
                            </div>

                            <div class=" p-3 rounded-lg ">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" class="todo-checkbox mt-1 w-4 h-4 text-purple-600 rounded" data-todo-id="3">
                                    <span class="ml-3">
                                        <p class="font-semibold text-sm text-gray-900">Follow Up</p>
                                        <p class="text-xs text-gray-600 mt-1">Check customer response</p>
                                    </span>
                                </label>
                            </div>

                            <div class=" p-3 rounded-lg ">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" class="todo-checkbox mt-1 w-4 h-4 text-green-600 rounded" data-todo-id="4" onclick="markAsCompleted()">
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
        const uploadedFiles = new Map();

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggleBtn = document.getElementById('toggleBtn');
            
            sidebarOpen = !sidebarOpen;
            
            if (sidebarOpen) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                toggleBtn.style.display = 'none';
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                toggleBtn.style.display = 'flex';
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

        function markAsCompleted() {
            const requestId = document.getElementById('request_id').value;
            
            if (confirm('Mark this request as completed?')) {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('id', requestId);
                formData.append('status', 'completed');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    alert('Request marked as completed!');
                    window.location.href = '?quoted=1';
                }).catch(error => {
                    console.error('Error:', error);
                    alert('Error updating status');
                });
            }
        }

        // File Upload Handling
        const fileInput = document.getElementById('fileInput');
        const uploadArea = fileInput?.parentElement;

        if (fileInput && uploadArea) {
            fileInput.addEventListener('change', handleFileSelect);

            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('border-green-500', 'bg-green-50');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('border-green-500', 'bg-green-50');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('border-green-500', 'bg-green-50');
                handleFiles(e.dataTransfer.files);
            });
        }

        function handleFileSelect(e) {
            handleFiles(e.target.files);
        }

        function handleFiles(files) {
            const maxSize = 10 * 1024 * 1024;
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'image/jpeg', 'image/png', 'image/gif', 'application/zip'];

            for (let file of files) {
                if (file.size > maxSize) {
                    alert(`File "${file.name}" is too large. Maximum size is 10MB.`);
                    continue;
                }

                if (!allowedTypes.includes(file.type) && !file.name.match(/\.(pdf|doc|docx|xls|xlsx|ppt|pptx|jpg|jpeg|png|gif|zip)$/i)) {
                    alert(`File type "${file.name}" is not allowed.`);
                    continue;
                }

                uploadedFiles.set(file.name, file);
            }

            updateFilesList();
        }

        function updateFilesList() {
            const filesList = document.getElementById('filesList');
            const filesContainer = document.getElementById('filesContainer');
            const uploadPrompt = document.getElementById('uploadPrompt');

            filesContainer.innerHTML = '';

            if (uploadedFiles.size === 0) {
                filesList.classList.add('hidden');
                uploadPrompt.style.display = 'block';
                return;
            }

            filesList.classList.remove('hidden');
            uploadPrompt.style.display = 'none';

            uploadedFiles.forEach((file, name) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'flex items-center justify-between bg-gray-50 p-3 rounded-lg border border-gray-200';
                fileItem.innerHTML = `
                    <div class="flex items-center flex-1">
                        <i class="fas fa-file text-gray-400 mr-3"></i>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">${name}</p>
                            <p class="text-xs text-gray-500">${(file.size / 1024).toFixed(2)} KB</p>
                        </div>
                    </div>
                    <button type="button" onclick="removeFile('${name}')" class="text-red-500 hover:text-red-700 ml-2">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                filesContainer.appendChild(fileItem);
            });
        }

        function removeFile(fileName) {
            uploadedFiles.delete(fileName);
            updateFilesList();
        }

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

                const formData = new FormData();
                formData.append('request_id', requestId);
                formData.append('email', email);
                formData.append('full_name', fullName);
                formData.append('message', message);

                uploadedFiles.forEach((file) => {
                    formData.append('reply_files[]', file);
                });

                try {
                    const response = await fetch('main-send-reply-page-1-A.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        status.classList.remove('hidden');
                        status.className = 'p-3 rounded-lg text-sm bg-green-100 text-green-800 border border-green-300';
                        status.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + (data.message || 'Reply sent successfully!');
                        form.reset();
                        uploadedFiles.clear();
                        updateFilesList();
                        
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

   // Todo Checkboxes with localStorage and status update
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
        checkbox.addEventListener('change', async () => {
            const storageKey = STORAGE_KEY + selectedRequestId;
            const todoId = checkbox.getAttribute('data-todo-id');
            const states = JSON.parse(localStorage.getItem(storageKey) || '{}');
            
            // If this is the "Mark Completed" checkbox (id = 4)
            if (todoId === '4') {
                if (!checkbox.checked) {
                    // Uncheck - revert to quoted status
                    states[todoId] = false;
                    localStorage.setItem(storageKey, JSON.stringify(states));
                    
                    // Update status to quoted in database
                    const formData = new FormData();
                    formData.append('action', 'update_status');
                    formData.append('id', selectedRequestId);
                    formData.append('status', 'quoted');

                    try {
                        await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });
                        
                        alert('Status reverted to quoted');
                        window.location.reload();
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error updating status');
                        checkbox.checked = true;
                    }
                } else {
                    // Just save the state when checked
                    states[todoId] = true;
                    localStorage.setItem(storageKey, JSON.stringify(states));
                }
            } else {
                // For other checkboxes, just save the state
                states[todoId] = checkbox.checked;
                localStorage.setItem(storageKey, JSON.stringify(states));
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadTodoStates();
    initTodoListeners();
});
        // Dashboard Functions
        function showDashboard(type) {
            const modal = document.getElementById('dashboardModal');
            const title = document.getElementById('dashboardTitle');
            const content = document.getElementById('dashboardContent');
            
            let requests = [];
            let titleText = '';
            let icon = '';
            let color = '';

            const allRequests = <?= json_encode($requests) ?>;

            if (type === 'all') {
                requests = allRequests;
                titleText = 'All Requests';
                icon = 'fa-inbox';
                color = 'blue';
            } else if (type === 'pending') {
                requests = allRequests.filter(r => r.status === 'pending');
                titleText = 'Pending Requests';
                icon = 'fa-clock';
                color = 'orange';
            } else if (type === 'quoted') {
                requests = allRequests.filter(r => r.status === 'quoted');
                titleText = 'Quoted Requests';
                icon = 'fa-file-invoice';
                color = 'yellow';
            } else if (type === 'completed') {
                requests = allRequests.filter(r => r.status === 'completed');
                titleText = 'Completed Requests';
                icon = 'fa-check-circle';
                color = 'green';
            }

            title.innerHTML = `<i class="fas ${icon} mr-2"></i>${titleText}`;
            
            if (requests.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-12">
                        <i class="fas ${icon} text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-500 text-lg">No requests found</p>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="space-y-3">
                        ${requests.map((req, idx) => `
                            <div class="p-4 border-l-4 border-l-${color}-500 bg-${color}-50 rounded-lg hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-bold text-gray-900">${req.full_name}</p>
                                        <p class="text-xs text-gray-600">${req.email}</p>
                                        <p class="text-xs text-gray-600">${req.phone}</p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded font-semibold ${
                                        req.status === 'pending' ? 'bg-orange-200 text-orange-800' :
                                        req.status === 'quoted' ? 'bg-yellow-200 text-yellow-800' :
                                        'bg-green-200 text-green-800'
                                    }">
                                        ${req.status.toUpperCase()}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-sm mb-2">
                                    <div>
                                        <span class="text-gray-600">Product:</span>
                                        <p class="font-semibold text-gray-900">${req.product_name || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Type:</span>
                                        <p class="font-semibold text-gray-900">${req.custom_type}</p>
                                    </div>
                                </div>
                                ${req.selected_color ? `<p class="text-xs text-gray-600"><strong>Color:</strong> ${req.selected_color}</p>` : ''}
                                ${req.selected_variant ? `<p class="text-xs text-gray-600"><strong>Variant:</strong> ${req.selected_variant}</p>` : ''}
                                <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200">
                                    <span class="text-xs text-gray-500">${new Date(req.created_at).toLocaleDateString()}</span>
                                    <a href="?id=${req.id}&reply=1" class="text-xs bg-${color}-500 hover:bg-${color}-600 text-white px-3 py-1 rounded font-semibold transition">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            modal.classList.remove('hidden');
        }

        function closeDashboard() {
            document.getElementById('dashboardModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('dashboardModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'dashboardModal') {
                closeDashboard();
            }
        });
    </script>
</body>
</html>