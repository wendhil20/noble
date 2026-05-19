<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$query = "
    SELECT 
        ccr.id, ccr.product_id, ccr.user_id, ccr.custom_type, ccr.specifications,
        ccr.full_name, ccr.email, ccr.phone, ccr.message, ccr.selected_color,
        ccr.selected_variant, ccr.agree_terms, ccr.status, ccr.created_at, ccr.updated_at,
        p.product_name, p.codename
    FROM custom_quote_requests ccr
    LEFT JOIN products p ON ccr.product_id = p.id
    ORDER BY ccr.created_at DESC
";
$result = $conn->query($query);
$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

$selectedId = isset($_GET['id']) ? intval($_GET['id']) : null;

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

$selectedRequest = null;
if ($selectedId) {
    $stmt = $conn->prepare("
        SELECT ccr.*, p.product_name, p.codename
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

$pending   = array_filter($requests, fn($r) => $r['status'] === 'pending');
$quoted    = array_filter($requests, fn($r) => $r['status'] === 'quoted');
$completed = array_filter($requests, fn($r) => $r['status'] === 'completed');
$total        = count($requests);
$pendingCount = count($pending);
$isReplyView  = $selectedRequest && isset($_GET['reply']);
$activeTab    = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize Requests</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap');
        * { font-family: 'DM Sans', sans-serif; box-sizing: border-box; }
        .mono { font-family: 'DM Mono', monospace; }
        .tab-btn.active { background: #1e293b; color: white; }
        .tab-btn { transition: all .15s ease; }
        .request-card { transition: all .15s ease; }
        .request-card:hover { transform: translateY(-1px); }
        .badge-pending  { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .badge-quoted   { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-completed{ background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .status-dot-pending   { background: #f97316; }
        .status-dot-quoted    { background: #eab308; }
        .status-dot-completed { background: #22c55e; }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
        textarea:focus, input:focus { outline: 2px solid #f97316; outline-offset: -1px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

<div class="max-w-screen-xl mx-auto px-4 py-8">

    <!-- PAGE HEADER -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-orange-500 mb-1">Admin Panel</p>
            <h1 class="text-2xl font-semibold text-slate-900">Customize Requests</h1>
            <p class="text-slate-500 text-sm mt-1">Manage customer customization inquiries and quotations</p>
        </div>
        <?php if ($pendingCount > 0): ?>
            <div class="inline-flex items-center gap-2 bg-orange-50 border border-orange-200 text-orange-700 text-sm font-medium px-4 py-2 rounded-lg">
                <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
                <?= $pendingCount ?> request<?= $pendingCount > 1 ? 's' : '' ?> need attention
            </div>
        <?php endif; ?>
    </div>

    <!-- STAT CARDS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        <?php
        $stats = [
            ['label' => 'Total',     'value' => $total,           'color' => 'slate',  'icon' => 'fa-inbox',        'tab' => 'all'],
            ['label' => 'Pending',   'value' => $pendingCount,    'color' => 'orange', 'icon' => 'fa-clock',        'tab' => 'pending'],
            ['label' => 'Quoted',    'value' => count($quoted),   'color' => 'yellow', 'icon' => 'fa-file-invoice', 'tab' => 'quoted'],
            ['label' => 'Completed', 'value' => count($completed),'color' => 'green',  'icon' => 'fa-check-circle', 'tab' => 'completed'],
        ];
        $colorMap = [
            'slate'  => ['card' => 'border-slate-200 hover:border-slate-300',  'num' => 'text-slate-800',  'icon' => 'text-slate-400'],
            'orange' => ['card' => 'border-orange-200 hover:border-orange-300','num' => 'text-orange-600', 'icon' => 'text-orange-300'],
            'yellow' => ['card' => 'border-yellow-200 hover:border-yellow-300','num' => 'text-yellow-600', 'icon' => 'text-yellow-300'],
            'green'  => ['card' => 'border-green-200 hover:border-green-300',  'num' => 'text-green-600',  'icon' => 'text-green-300'],
        ];
        foreach ($stats as $s):
            $c = $colorMap[$s['color']];
        ?>
        <button onclick="switchTab('<?= $s['tab'] ?>')"
            class="bg-white rounded-xl border <?= $c['card'] ?> p-5 text-left transition hover:shadow-sm group w-full">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2"><?= $s['label'] ?></p>
                    <p class="text-3xl font-semibold <?= $c['num'] ?>"><?= $s['value'] ?></p>
                </div>
                <i class="fas <?= $s['icon'] ?> text-2xl <?= $c['icon'] ?>"></i>
            </div>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- MAIN PANEL -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <?php if ($isReplyView): ?>
        <!-- ===== REPLY VIEW ===== -->
        <div class="border-b border-slate-100 px-6 py-4 flex items-center gap-3">
            <a href="?tab=quoted" class="text-slate-400 hover:text-slate-700 transition">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="font-semibold text-slate-900">Reply to Request #<?= $selectedRequest['id'] ?></h2>
                <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($selectedRequest['full_name']) ?> · <?= htmlspecialchars($selectedRequest['email']) ?></p>
            </div>
            <span class="ml-auto badge-quoted text-xs font-semibold px-3 py-1 rounded-full">QUOTED</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
            <!-- Left: Request Summary -->
            <div class="lg:col-span-2 p-6 space-y-5 bg-slate-50">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">Customer</p>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-700 font-semibold text-sm">
                            <?= strtoupper(substr($selectedRequest['full_name'], 0, 2)) ?>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900 text-sm"><?= htmlspecialchars($selectedRequest['full_name']) ?></p>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars($selectedRequest['email']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <i class="fas fa-phone text-slate-300"></i>
                        <?= htmlspecialchars($selectedRequest['phone']) ?>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">Product</p>
                    <p class="font-semibold text-slate-900 text-sm"><?= htmlspecialchars($selectedRequest['product_name'] ?? 'N/A') ?></p>
                    <span class="inline-block mt-1 text-xs bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded-md">
                        <?= ucfirst(str_replace('_', ' ', $selectedRequest['custom_type'])) ?>
                    </span>
                </div>

                <?php if (!empty($selectedRequest['selected_color']) || !empty($selectedRequest['selected_variant']) || !empty($selectedRequest['specifications'])): ?>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">Specifications</p>
                    <div class="space-y-2 text-sm">
                        <?php if (!empty($selectedRequest['selected_color'])): ?>
                        <div class="flex gap-2">
                            <span class="text-slate-400 min-w-14">Color</span>
                            <span class="text-slate-800 font-medium"><?= htmlspecialchars($selectedRequest['selected_color']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($selectedRequest['selected_variant'])): ?>
                        <div class="flex gap-2">
                            <span class="text-slate-400 min-w-14">Variant</span>
                            <span class="text-slate-800 font-medium"><?= htmlspecialchars($selectedRequest['selected_variant']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($selectedRequest['specifications'])): ?>
                        <div>
                            <span class="text-slate-400 block mb-1">Notes</span>
                            <p class="text-slate-800 text-xs leading-relaxed bg-white border border-slate-100 rounded-lg p-3">
                                <?= nl2br(htmlspecialchars($selectedRequest['specifications'])) ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($selectedRequest['message'])): ?>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">Customer's Message</p>
                    <p class="text-sm text-slate-700 leading-relaxed bg-white border border-slate-100 rounded-lg p-3">
                        <?= nl2br(htmlspecialchars($selectedRequest['message'])) ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- To-Do Checklist -->
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">Checklist</p>
                    <div class="space-y-2">
                        <?php
                        $todos = [
                            ['id' => 1, 'label' => 'Review request details'],
                            ['id' => 2, 'label' => 'Prepare price quotation'],
                            ['id' => 3, 'label' => 'Send reply to customer'],
                            ['id' => 4, 'label' => 'Mark as completed', 'complete' => true],
                        ];
                        foreach ($todos as $todo):
                        ?>
                        <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer group">
                            <input type="checkbox"
                                class="todo-checkbox w-4 h-4 rounded accent-orange-500"
                                data-todo-id="<?= $todo['id'] ?>"
                                <?= isset($todo['complete']) ? 'onclick="markAsCompleted()"' : '' ?>>
                            <span class="group-has-[:checked]:line-through group-has-[:checked]:text-slate-400 transition">
                                <?= $todo['label'] ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Reply Form -->
            <div class="lg:col-span-3 p-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-4">Your Reply</p>
                <form id="replyForm" class="space-y-5">
                    <input type="hidden" name="request_id" id="request_id" value="<?= $selectedRequest['id'] ?>">
                    <input type="hidden" id="reply_email" value="<?= htmlspecialchars($selectedRequest['email']) ?>">
                    <input type="hidden" id="reply_name" value="<?= htmlspecialchars($selectedRequest['full_name']) ?>">

                    <textarea name="reply_message"
                        rows="10"
                        placeholder="Type your reply, quotation details, pricing, lead times, etc..."
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder-slate-400 resize-none transition"
                        required></textarea>

                    <!-- File Attach -->
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-2">Attachments <span class="normal-case font-normal text-slate-400">(optional)</span></p>
                        <div id="dropZone"
                            class="border-2 border-dashed border-slate-200 hover:border-orange-300 rounded-xl p-6 text-center transition cursor-pointer bg-slate-50 hover:bg-orange-50"
                            onclick="document.getElementById('fileInput').click()">
                            <input type="file" id="fileInput" multiple
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip"
                                class="hidden">
                            <i class="fas fa-paperclip text-slate-300 text-2xl mb-2 block"></i>
                            <p class="text-sm font-medium text-slate-600">Click to attach files</p>
                            <p class="text-xs text-slate-400 mt-1">PDF, Word, Excel, Images, ZIP · Max 10MB each</p>
                        </div>
                        <div id="filesList" class="hidden mt-3 space-y-2"></div>
                    </div>

                    <div id="replyStatus" class="hidden text-sm rounded-xl px-4 py-3"></div>

                    <div class="flex gap-3 pt-1">
                        <button type="submit"
                            class="flex-1 bg-slate-900 hover:bg-slate-700 text-white font-semibold text-sm py-3 px-6 rounded-xl transition flex items-center justify-center gap-2">
                            <i class="fas fa-paper-plane"></i>Send Reply
                        </button>
                        <a href="?tab=quoted"
                            class="px-6 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm py-3 rounded-xl transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- ===== LIST VIEW ===== -->

        <!-- Tabs -->
        <div class="border-b border-slate-100 px-6 pt-5">
            <div class="flex gap-1">
                <?php
                $tabs = [
                    'pending'   => ['label' => 'Pending',   'count' => $pendingCount,    'active_class' => 'bg-orange-500 text-white border-orange-500'],
                    'quoted'    => ['label' => 'Quoted',    'count' => count($quoted),   'active_class' => 'bg-yellow-500 text-white border-yellow-500'],
                    'completed' => ['label' => 'Completed', 'count' => count($completed),'active_class' => 'bg-green-600 text-white border-green-600'],
                    'all'       => ['label' => 'All',       'count' => $total,           'active_class' => 'bg-slate-800 text-white border-slate-800'],
                ];
                foreach ($tabs as $key => $tab):
                    $isActive = $activeTab === $key;
                    $baseClass = "tab-btn border px-4 py-2 rounded-t-lg text-sm font-medium flex items-center gap-2 -mb-px cursor-pointer " .
                        ($isActive ? $tab['active_class'] . ' border-b-transparent' : 'bg-white text-slate-600 border-slate-200 hover:text-slate-900 hover:bg-slate-50');
                ?>
                <button class="<?= $baseClass ?>" onclick="switchTab('<?= $key ?>')" data-tab="<?= $key ?>">
                    <?= $tab['label'] ?>
                    <span class="<?= $isActive ? 'bg-white/20' : 'bg-slate-100 text-slate-600' ?> text-xs px-1.5 py-0.5 rounded-md font-semibold">
                        <?= $tab['count'] ?>
                    </span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <?php
            $allTabData = [
                'pending'   => $pending,
                'quoted'    => $quoted,
                'completed' => $completed,
                'all'       => $requests,
            ];
            foreach ($allTabData as $tabKey => $tabData):
                $isVisible = $activeTab === $tabKey;
            ?>
            <div id="tab-<?= $tabKey ?>" class="<?= $isVisible ? '' : 'hidden' ?>">
                <?php if (empty($tabData)): ?>
                    <div class="flex flex-col items-center justify-center py-20 text-slate-400">
                        <i class="fas fa-inbox text-4xl mb-3 opacity-30"></i>
                        <p class="font-medium text-slate-500">No <?= $tabKey ?> requests</p>
                        <p class="text-xs mt-1">They'll show up here when available</p>
                    </div>
                <?php else: ?>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-xs text-slate-500 uppercase tracking-wide font-semibold">
                            <th class="text-left px-6 py-3 font-semibold">Customer</th>
                            <th class="text-left px-4 py-3 font-semibold hidden md:table-cell">Product</th>
                            <th class="text-left px-4 py-3 font-semibold hidden lg:table-cell">Type</th>
                            <th class="text-left px-4 py-3 font-semibold hidden lg:table-cell">Date</th>
                            <th class="text-left px-4 py-3 font-semibold">Status</th>
                            <th class="text-right px-6 py-3 font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($tabData as $req): ?>
                        <tr class="hover:bg-slate-50 transition group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-semibold text-xs flex-shrink-0">
                                        <?= strtoupper(substr($req['full_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-900 truncate max-w-36"><?= htmlspecialchars($req['full_name']) ?></p>
                                        <p class="text-xs text-slate-400 truncate max-w-36"><?= htmlspecialchars($req['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 hidden md:table-cell">
                                <p class="text-slate-700 font-medium truncate max-w-36"><?= htmlspecialchars($req['product_name'] ?? '—') ?></p>
                                <?php if ($req['codename']): ?>
                                    <p class="text-xs text-slate-400 mono"><?= htmlspecialchars($req['codename']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 hidden lg:table-cell">
                                <span class="text-xs bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded-md font-medium">
                                    <?= ucfirst(str_replace('_', ' ', $req['custom_type'])) ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 hidden lg:table-cell text-slate-400 text-xs">
                                <?= date('M d, Y', strtotime($req['created_at'])) ?>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="status-dot-<?= $req['status'] ?> w-1.5 h-1.5 rounded-full"></span>
                                    <span class="badge-<?= $req['status'] ?> text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                        <?= strtoupper($req['status']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($req['status'] === 'pending'): ?>
                                    <button onclick="updateStatusToQuoted(<?= $req['id'] ?>)"
                                        class="text-xs bg-orange-500 hover:bg-orange-600 text-white font-semibold px-3 py-1.5 rounded-lg transition">
                                        <i class="fas fa-reply mr-1"></i>Respond
                                    </button>
                                <?php elseif ($req['status'] === 'quoted'): ?>
                                    <a href="?tab=quoted&id=<?= $req['id'] ?>&reply=1"
                                        class="text-xs bg-yellow-500 hover:bg-yellow-600 text-white font-semibold px-3 py-1.5 rounded-lg transition">
                                        <i class="fas fa-paper-plane mr-1"></i>Reply
                                    </a>
                                <?php else: ?>
                                    <a href="?tab=completed&id=<?= $req['id'] ?>&reply=1"
                                        class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-1.5 rounded-lg transition">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const uploadedFiles = new Map();

    // Tab switching
    function switchTab(tab) {
        document.querySelectorAll('[data-tab]').forEach(btn => {
            btn.classList.remove('bg-orange-500','bg-yellow-500','bg-green-600','bg-slate-800','text-white','border-orange-500','border-yellow-500','border-green-600','border-slate-800','border-b-transparent');
            btn.classList.add('bg-white','text-slate-600','border-slate-200');
        });
        document.querySelectorAll('[id^="tab-"]').forEach(el => el.classList.add('hidden'));

        const active = document.querySelector(`[data-tab="${tab}"]`);
        const panel  = document.getElementById(`tab-${tab}`);
        if (active && panel) {
            const colorMap = {
                pending:   ['bg-orange-500','border-orange-500'],
                quoted:    ['bg-yellow-500','border-yellow-500'],
                completed: ['bg-green-600','border-green-600'],
                all:       ['bg-slate-800','border-slate-800'],
            };
            active.classList.remove('bg-white','text-slate-600','border-slate-200');
            active.classList.add(...(colorMap[tab] || ['bg-slate-800','border-slate-800']), 'text-white','border-b-transparent');
            panel.classList.remove('hidden');
        }
        history.replaceState(null,'', `?tab=${tab}`);
    }

    function updateStatusToQuoted(requestId) {
        const fd = new FormData();
        fd.append('action','update_status');
        fd.append('id', requestId);
        fd.append('status','quoted');
        fetch(window.location.href, { method:'POST', body: fd })
            .finally(() => { window.location.href = `?tab=quoted&id=${requestId}&reply=1`; });
    }

    function markAsCompleted() {
        const requestId = document.getElementById('request_id')?.value;
        if (!requestId) return;
        if (!confirm('Mark this request as completed?')) {
            event.target.checked = false; return;
        }
        const fd = new FormData();
        fd.append('action','update_status');
        fd.append('id', requestId);
        fd.append('status','completed');
        fetch(window.location.href, { method:'POST', body: fd })
            .finally(() => { window.location.href = '?tab=completed'; });
    }

    // File upload
    const fileInput = document.getElementById('fileInput');
    const dropZone  = document.getElementById('dropZone');

    if (fileInput && dropZone) {
        fileInput.addEventListener('change', e => handleFiles(e.target.files));
        dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('border-orange-400','bg-orange-50'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-orange-400','bg-orange-50'));
        dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('border-orange-400','bg-orange-50'); handleFiles(e.dataTransfer.files); });
    }

    function handleFiles(files) {
        const maxSize = 10 * 1024 * 1024;
        for (let file of files) {
            if (file.size > maxSize) { alert(`"${file.name}" exceeds 10MB`); continue; }
            uploadedFiles.set(file.name, file);
        }
        renderFilesList();
    }

    function renderFilesList() {
        const list = document.getElementById('filesList');
        if (!list) return;
        list.innerHTML = '';
        if (uploadedFiles.size === 0) { list.classList.add('hidden'); return; }
        list.classList.remove('hidden');
        uploadedFiles.forEach((file, name) => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between bg-slate-50 border border-slate-200 px-3 py-2 rounded-lg text-sm';
            item.innerHTML = `<div class="flex items-center gap-2"><i class="fas fa-file text-slate-300"></i><span class="text-slate-700">${name}</span><span class="text-xs text-slate-400">${(file.size/1024).toFixed(1)} KB</span></div><button type="button" onclick="removeFile('${name}')" class="text-slate-400 hover:text-red-500 transition"><i class="fas fa-times"></i></button>`;
            list.appendChild(item);
        });
    }

    function removeFile(name) { uploadedFiles.delete(name); renderFilesList(); }

    // Reply form
    const replyForm = document.getElementById('replyForm');
    if (replyForm) {
        replyForm.addEventListener('submit', async e => {
            e.preventDefault();
            const form = e.target;
            const status = document.getElementById('replyStatus');
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';

            const fd = new FormData();
            fd.append('request_id', document.getElementById('request_id').value);
            fd.append('email',      document.getElementById('reply_email').value);
            fd.append('full_name',  document.getElementById('reply_name').value);
            fd.append('message',    form.querySelector('textarea[name="reply_message"]').value);
            uploadedFiles.forEach(file => fd.append('reply_files[]', file));

            try {
                const res  = await fetch('main-send-reply-page-1-A.php', { method:'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    status.className = 'text-sm rounded-xl px-4 py-3 bg-green-50 border border-green-200 text-green-800';
                    status.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + (data.message || 'Reply sent!');
                    status.classList.remove('hidden');
                    form.reset(); uploadedFiles.clear(); renderFilesList();
                    setTimeout(() => { window.location.href = '?tab=quoted'; }, 2000);
                } else throw new Error(data.error || 'Unknown error');
            } catch (err) {
                status.className = 'text-sm rounded-xl px-4 py-3 bg-red-50 border border-red-200 text-red-800';
                status.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + err.message;
                status.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Reply';
            }
        });
    }

    // Persist todo checkboxes
    const STORAGE_KEY = 'requestTodos_';
    function loadTodos() {
        const id = new URLSearchParams(location.search).get('id');
        if (!id) return;
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY + id) || '{}');
        document.querySelectorAll('.todo-checkbox').forEach(cb => {
            if (saved[cb.dataset.todoId]) cb.checked = true;
        });
    }
    function initTodos() {
        const id = new URLSearchParams(location.search).get('id');
        if (!id) return;
        document.querySelectorAll('.todo-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                const saved = JSON.parse(localStorage.getItem(STORAGE_KEY + id) || '{}');
                saved[cb.dataset.todoId] = cb.checked;
                localStorage.setItem(STORAGE_KEY + id, JSON.stringify(saved));
            });
        });
    }
    document.addEventListener('DOMContentLoaded', () => { loadTodos(); initTodos(); });
</script>
</body>
</html>