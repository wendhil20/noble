<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

require_role(['superadmin', 'hr']);

$departments = ['superadmin', 'sales', 'accountant', 'hr', 'warehouse', 'logistic', 'productspecialist'];

$department_subroles = [
    'sales'     => [],
    'accountant' => ['document_controller'],
    'hr'        => [],
    'warehouse' => ['warehouse_receiver', 'warehouse_staff'],
    'logistic'  => ['dispatcher'],
];

$q = "SELECT id, fullname, email, lvl, IFNULL(is_head,0) AS is_head, subrole,
      IFNULL(commission_rate, 0.00) AS commission_rate,
      CASE WHEN e_signature IS NOT NULL THEN 1 ELSE 0 END as has_signature
      FROM nobleaccount
      ORDER BY lvl, fullname";
$res = mysqli_query($conn, $q);

$groups = [];
$heads  = [];
while ($r = mysqli_fetch_assoc($res)) {
    $lvl = strtolower($r['lvl']);
    $groups[$lvl][] = $r;
    if ((int) $r['is_head'] === 1) $heads[] = $r;
}

$dept_labels = [
    'superadmin'       => 'Super Admin',
    'sales'            => 'Sales',
    'accountant'       => 'Accountant',
    'hr'               => 'HR',
    'warehouse'        => 'Warehouse',
    'logistic'         => 'Logistics',
    'productspecialist'=> 'Product Specialist',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Manage Department Heads — Noble Enterprise</title>
</head>
<body class="min-h-screen bg-gray-50 text-gray-800">

<?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Department Heads</h1>
        <p class="text-sm text-gray-500 mt-1">Assign or remove department heads. Manage subroles, commissions, and e-signatures.</p>
    </div>

    <!-- Search + summary row -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
        <div class="relative flex-1 max-w-sm">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input id="searchInput" type="search" placeholder="Search by name or email…"
                class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent">
        </div>
        <div class="text-sm text-gray-500">
            <span id="headCount" class="font-semibold text-orange-600">0</span> heads assigned
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex overflow-x-auto gap-1" id="tabNav" aria-label="Departments">
            <button type="button"
                class="tab-btn flex-shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 border-orange-500 text-orange-600 bg-white rounded-t-lg"
                data-filter="">
                All
                <span class="ml-1.5 bg-orange-100 text-orange-700 text-xs px-1.5 py-0.5 rounded-full">
                    <?= array_sum(array_map('count', $groups)) ?>
                </span>
            </button>
            <?php foreach ($departments as $d):
                $count = isset($groups[$d]) ? count($groups[$d]) : 0;
                $label = $dept_labels[$d] ?? ucwords($d);
                $hasHead = false;
                foreach ($groups[$d] ?? [] as $m) {
                    if ((int) $m['is_head'] === 1) { $hasHead = true; break; }
                }
            ?>
            <button type="button"
                class="tab-btn flex-shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 rounded-t-lg relative"
                data-filter="<?= htmlspecialchars($d) ?>">
                <?= htmlspecialchars($label) ?>
                <span class="ml-1.5 bg-gray-100 text-gray-600 text-xs px-1.5 py-0.5 rounded-full"><?= $count ?></span>
                <?php if ($hasHead): ?>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-orange-400 rounded-full" title="Has head"></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Main content grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left: Department member lists -->
        <div class="lg:col-span-2 space-y-4" id="departmentsContainer">
            <?php foreach ($departments as $dept):
                $members = $groups[$dept] ?? [];
                $label   = $dept_labels[$dept] ?? ucwords($dept);
                $headCount = count(array_filter($members, fn($m) => (int)$m['is_head'] === 1));
            ?>
            <section class="bg-white border border-gray-200 rounded-xl dept-section" data-dept="<?= htmlspecialchars($dept) ?>">

                <!-- Section header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
                            <i class="fas fa-users text-orange-400 text-sm"></i>
                        </div>
                        <div>
                            <h2 class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($label) ?></h2>
                            <p class="text-xs text-gray-400"><?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?>
                                <?php if ($headCount > 0): ?>
                                · <span class="text-orange-500"><?= $headCount ?> head</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Member rows -->
                <ul class="divide-y divide-gray-50 member-list">
                    <?php if (count($members) === 0): ?>
                    <li class="px-5 py-6 text-center text-sm text-gray-400">No members in this department.</li>
                    <?php else:
                        foreach ($members as $m):
                            $id      = (int) $m['id'];
                            $is_head = (int) $m['is_head'];
                    ?>
                    <li data-id="<?= $id ?>" data-dept="<?= htmlspecialchars($dept) ?>" data-is-head="<?= $is_head ?>"
                        class="px-5 py-4 flex items-start gap-4 hover:bg-gray-50 transition">

                        <!-- Signature avatar -->
                        <div class="flex-shrink-0">
                            <?php if ((int) $m['has_signature'] === 1): ?>
                            <img src="view_signature.php?id=<?= $id ?>" alt="Signature"
                                class="w-12 h-12 object-contain border border-gray-200 rounded-lg bg-white"/>
                            <?php else: ?>
                            <div class="w-12 h-12 flex items-center justify-center border-2 border-dashed border-gray-200 rounded-lg bg-gray-50">
                                <i class="fas fa-signature text-gray-300 text-sm"></i>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($m['fullname']) ?></span>
                                <?php if ($is_head === 1): ?>
                                <span class="head-badge inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs px-2 py-0.5 rounded-full font-medium">
                                    <i class="fas fa-crown text-orange-400" style="font-size:10px"></i> Head
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5 truncate"><?= htmlspecialchars($m['email']) ?></p>
                            <?php if (!empty($m['subrole'])): ?>
                            <p class="text-xs text-blue-500 mt-1"><i class="fas fa-tag mr-1"></i><?= htmlspecialchars($m['subrole']) ?></p>
                            <?php endif; ?>
                            <?php if ($dept === 'sales' && isset($m['commission_rate'])): ?>
                            <p class="text-xs text-green-600 mt-1 font-medium"><i class="fas fa-percent mr-1"></i>Commission: <?= number_format($m['commission_rate'], 2) ?>%</p>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="flex-shrink-0 flex flex-wrap gap-2 items-center">
                            <?php if ($is_head === 1): ?>
                            <button type="button" class="remove-head text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition" data-id="<?= $id ?>">
                                <i class="fas fa-times mr-1"></i>Remove Head
                            </button>
                            <?php else: ?>
                            <button type="button" class="set-head text-xs px-2.5 py-1.5 rounded-lg border border-orange-200 text-orange-600 hover:bg-orange-50 transition" data-id="<?= $id ?>">
                                <i class="fas fa-crown mr-1"></i>Set Head
                            </button>
                            <?php endif; ?>

                            <button type="button" class="upload-signature text-xs px-2.5 py-1.5 rounded-lg border border-purple-200 text-purple-600 hover:bg-purple-50 transition" data-id="<?= $id ?>">
                                <i class="fas fa-upload mr-1"></i>Signature
                            </button>

                            <?php if ($dept !== 'sales'): ?>
                            <button type="button" class="edit-subrole text-xs px-2.5 py-1.5 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 transition"
                                data-id="<?= $id ?>" data-subrole="<?= htmlspecialchars($m['subrole'] ?? '') ?>">
                                <i class="fas fa-user-tag mr-1"></i>Role
                            </button>
                            <?php else: ?>
                            <button type="button" class="edit-commission text-xs px-2.5 py-1.5 rounded-lg border border-green-200 text-green-600 hover:bg-green-50 transition"
                                data-id="<?= $id ?>" data-commission="<?= htmlspecialchars($m['commission_rate'] ?? '0.00') ?>">
                                <i class="fas fa-percent mr-1"></i>Commission
                            </button>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; endif; ?>
                </ul>
            </section>
            <?php endforeach; ?>
        </div>

        <!-- Right: Heads sidebar -->
        <aside class="space-y-4">

            <!-- Heads list card -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
                        <i class="fas fa-crown text-orange-400 text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800 text-sm">Department Heads</h3>
                        <p class="text-xs text-gray-400">Click View to locate member</p>
                    </div>
                </div>
                <ul id="headsList" class="divide-y divide-gray-50">
                    <?php
                    $visible_heads = array_filter($heads, fn($h) => strtolower($h['lvl']) !== 'supplier');
                    if (count($visible_heads) === 0): ?>
                    <li class="px-5 py-6 text-sm text-gray-400 text-center">No heads assigned yet.</li>
                    <?php else:
                        foreach ($visible_heads as $h): ?>
                    <li data-id="<?= (int) $h['id'] ?>" data-dept="<?= htmlspecialchars(strtolower($h['lvl'])) ?>"
                        class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($h['fullname']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($dept_labels[strtolower($h['lvl'])] ?? ucwords($h['lvl'])) ?></p>
                        </div>
                        <div class="flex gap-2 flex-shrink-0 ml-3">
                            <button type="button" class="goto-member text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition" data-id="<?= (int) $h['id'] ?>">View</button>
                            <button type="button" class="remove-head text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition" data-id="<?= (int) $h['id'] ?>">Remove</button>
                        </div>
                    </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>

            <!-- Note card -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm text-amber-800">
                <p class="font-medium mb-1"><i class="fas fa-info-circle mr-1"></i>Note</p>
                <p class="text-xs text-amber-700 leading-relaxed">Assigning a new head will automatically remove the previous head for that department.</p>
            </div>
        </aside>
    </div>
</div>

<!-- ── Confirm Modal ── -->
<div id="confirmModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <h4 id="confirmTitle" class="font-semibold text-gray-800 mb-2">Confirm</h4>
        <p id="confirmMessage" class="text-sm text-gray-600 mb-5">Are you sure?</p>
        <div class="flex justify-end gap-2">
            <button id="confirmCancel" type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
            <button id="confirmOk" type="button" class="px-4 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium">Yes, continue</button>
        </div>
    </div>
</div>

<!-- ── Subrole Modal ── -->
<div id="subroleModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <h4 class="font-semibold text-gray-800 mb-1">Edit Subrole</h4>
        <p id="subroleDeptName" class="text-sm text-gray-500 mb-4">Select a role</p>
        <div class="mb-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Role</label>
            <select id="subroleSelect" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <option value="">— No Role —</option>
            </select>
        </div>
        <label class="flex items-center gap-2 mb-3 text-sm text-gray-600">
            <input type="checkbox" id="subroleCustomToggle" class="rounded"> Use custom role
        </label>
        <div id="subroleCustomContainer" class="hidden mb-3">
            <input type="text" id="subroleCustomInput" placeholder="Enter custom role…"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
        </div>
        <div class="flex justify-end gap-2">
            <button id="subroleCancel" type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
            <button id="subroleSave" type="button" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">Save</button>
        </div>
    </div>
</div>

<!-- ── Commission Modal ── -->
<div id="commissionModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <h4 class="font-semibold text-gray-800 mb-1">Edit Commission Rate</h4>
        <p class="text-sm text-gray-500 mb-4">Set commission percentage for this sales member</p>
        <div class="relative mb-1">
            <input type="number" id="commissionInput" step="0.01" min="0" max="100" placeholder="0.00"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
        </div>
        <p class="text-xs text-gray-400 mb-4">Value between 0.00 – 100.00</p>
        <div class="flex justify-end gap-2">
            <button id="commissionCancel" type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
            <button id="commissionSave" type="button" class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium">Save</button>
        </div>
    </div>
</div>

<!-- ── Signature Modal ── -->
<div id="signatureModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <h4 class="font-semibold text-gray-800 mb-1">Upload E-Signature</h4>
        <p class="text-sm text-gray-500 mb-4">PNG, JPG, or GIF · max 2MB</p>
        <div class="mb-3">
            <input type="file" id="signatureFile" accept="image/png,image/jpeg,image/jpg,image/gif"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
        </div>
        <div id="signaturePreview" class="hidden mb-4 border border-gray-200 rounded-lg p-3 bg-gray-50">
            <img id="signaturePreviewImg" src="" alt="Preview" class="max-h-24 mx-auto"/>
        </div>
        <div class="flex justify-end gap-2">
            <button id="signatureCancel" type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
            <button id="signatureSave" type="button" disabled class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium disabled:opacity-50">Upload</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-6 right-6 z-50 hidden">
    <div id="toastContent" class="px-4 py-2.5 rounded-lg shadow-lg text-sm text-white"></div>
</div>

<script>
const departmentSubroles = <?= json_encode($department_subroles) ?>;

document.addEventListener('DOMContentLoaded', function () {
    const $ = s => document.querySelector(s);
    const $$ = s => Array.from(document.querySelectorAll(s));

    const tabBtns         = $$('.tab-btn');
    const deptSections    = $$('.dept-section');
    const searchInput     = $('#searchInput');
    const headsListEl     = $('#headsList');
    const headCountEl     = $('#headCount');
    const confirmModal    = $('#confirmModal');
    const confirmTitle    = $('#confirmTitle');
    const confirmMessage  = $('#confirmMessage');
    const confirmOk       = $('#confirmOk');
    const confirmCancel   = $('#confirmCancel');
    const subroleModal    = $('#subroleModal');
    const subroleSelect   = $('#subroleSelect');
    const subroleCustomToggle   = $('#subroleCustomToggle');
    const subroleCustomContainer = $('#subroleCustomContainer');
    const subroleCustomInput    = $('#subroleCustomInput');
    const subroleDeptName = $('#subroleDeptName');
    const subroleSave     = $('#subroleSave');
    const subroleCancel   = $('#subroleCancel');
    const commissionModal = $('#commissionModal');
    const commissionInput = $('#commissionInput');
    const commissionSave  = $('#commissionSave');
    const commissionCancel = $('#commissionCancel');
    const signatureModal  = $('#signatureModal');
    const signatureFile   = $('#signatureFile');
    const signaturePreview     = $('#signaturePreview');
    const signaturePreviewImg  = $('#signaturePreviewImg');
    const signatureSave   = $('#signatureSave');
    const signatureCancel = $('#signatureCancel');
    let currentSignatureId = null;
    const toast        = $('#toast');
    const toastContent = $('#toastContent');

    // ── Toast ──
    function showToast(msg, type = 'success') {
        toastContent.textContent = msg;
        toastContent.className = 'px-4 py-2.5 rounded-lg shadow-lg text-sm text-white ' +
            (type === 'error' ? 'bg-red-600' : 'bg-green-600');
        toast.classList.remove('hidden');
        clearTimeout(window._t);
        window._t = setTimeout(() => toast.classList.add('hidden'), 3000);
    }

    // ── Tab filter ──
    function applyFilter(dept) {
        deptSections.forEach(sec => {
            sec.style.display = (!dept || sec.dataset.dept === dept) ? '' : 'none';
        });
        tabBtns.forEach(btn => {
            const active = (btn.dataset.filter || '') === dept;
            btn.classList.toggle('border-orange-500', active);
            btn.classList.toggle('text-orange-600', active);
            btn.classList.toggle('bg-white', active);
            btn.classList.toggle('border-transparent', !active);
            btn.classList.toggle('text-gray-500', !active);
        });
        const params = new URLSearchParams(window.location.search);
        dept ? params.set('dept', dept) : params.delete('dept');
        history.replaceState(null, '', window.location.pathname + (params.toString() ? '?' + params : ''));
        refreshHeadsList();
    }

    // ── Heads sidebar refresh ──
    function refreshHeadsList() {
        const headRows = $$('li[data-id]').filter(r =>
            r.dataset.isHead === '1' && r.dataset.dept !== 'supplier'
        );
        headCountEl.textContent = headRows.length;

        if (headRows.length === 0) {
            headsListEl.innerHTML = '<li class="px-5 py-6 text-sm text-gray-400 text-center">No heads assigned yet.</li>';
            return;
        }

        const deptLabels = <?= json_encode($dept_labels) ?>;
        headsListEl.innerHTML = '';
        headRows.forEach(r => {
            const id   = r.dataset.id;
            const name = r.querySelector('.font-medium')?.textContent.trim() || '';
            const dept = r.dataset.dept || '';
            const label = deptLabels[dept] || dept.replace(/\b\w/g, c => c.toUpperCase());
            const li = document.createElement('li');
            li.dataset.id   = id;
            li.dataset.dept = dept;
            li.className = 'flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition';
            li.innerHTML = `
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">${name}</p>
                    <p class="text-xs text-gray-400">${label}</p>
                </div>
                <div class="flex gap-2 flex-shrink-0 ml-3">
                    <button type="button" class="goto-member text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition" data-id="${id}">View</button>
                    <button type="button" class="remove-head text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition" data-id="${id}">Remove</button>
                </div>`;
            headsListEl.appendChild(li);
        });
    }

    // ── AJAX helper ──
    async function sendAction(accountId, action, extraData = {}) {
        let body = `account_id=${encodeURIComponent(accountId)}&action=${encodeURIComponent(action)}`;
        for (const k in extraData) body += `&${encodeURIComponent(k)}=${encodeURIComponent(extraData[k])}`;
        const resp = await fetch('<?= BASE_URL ?>/manageheadaccount', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        });
        return resp.json();
    }

    // ── Confirm dialog ──
    function confirmDialog(title, message) {
        confirmTitle.textContent   = title;
        confirmMessage.textContent = message;
        confirmModal.classList.remove('hidden');
        return new Promise(resolve => {
            const cleanup = () => {
                confirmModal.classList.add('hidden');
                confirmOk.removeEventListener('click', ok);
                confirmCancel.removeEventListener('click', cancel);
            };
            const ok     = () => { cleanup(); resolve(true);  };
            const cancel = () => { cleanup(); resolve(false); };
            confirmOk.addEventListener('click', ok);
            confirmCancel.addEventListener('click', cancel);
        });
    }

    // ── Signature file preview ──
    signatureFile.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) { signaturePreview.classList.add('hidden'); signatureSave.disabled = true; return; }
        if (file.size > 2 * 1024 * 1024) {
            showToast('File must be under 2MB', 'error');
            this.value = '';
            signatureSave.disabled = true;
            signaturePreview.classList.add('hidden');
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            signaturePreviewImg.src = e.target.result;
            signaturePreview.classList.remove('hidden');
            signatureSave.disabled = false;
        };
        reader.readAsDataURL(file);
    });

    // ── Delegated click handler ──
    document.addEventListener('click', async (e) => {
        const tabBtn          = e.target.closest('.tab-btn');
        const setBtn          = e.target.closest('.set-head');
        const removeBtn       = e.target.closest('.remove-head');
        const gotoBtn         = e.target.closest('.goto-member');
        const uploadSigBtn    = e.target.closest('.upload-signature');
        const editSubroleBtn  = e.target.closest('.edit-subrole');
        const editCommBtn     = e.target.closest('.edit-commission');

        // Tab filter
        if (tabBtn) { applyFilter(tabBtn.dataset.filter || ''); return; }

        // Goto member
        if (gotoBtn) {
            const target = document.querySelector(`li[data-id="${gotoBtn.dataset.id}"]`);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.classList.add('ring-2', 'ring-orange-300');
                setTimeout(() => target.classList.remove('ring-2', 'ring-orange-300'), 1500);
            }
            return;
        }

        // Upload Signature
        if (uploadSigBtn) {
            currentSignatureId = uploadSigBtn.dataset.id;
            signatureFile.value = '';
            signaturePreview.classList.add('hidden');
            signatureSave.disabled = true;
            signatureModal.classList.remove('hidden');

            const cleanup = () => {
                signatureModal.classList.add('hidden');
                signatureFile.value = '';
                signaturePreview.classList.add('hidden');
                currentSignatureId = null;
                signatureSave.removeEventListener('click', saveH);
                signatureCancel.removeEventListener('click', cancelH);
            };
            const saveH = async () => {
                const file = signatureFile.files[0];
                if (!file) { showToast('Select a file', 'error'); return; }
                signatureSave.disabled = true;
                signatureSave.textContent = 'Uploading…';
                const fd = new FormData();
                fd.append('account_id', currentSignatureId);
                fd.append('action', 'upload_signature');
                fd.append('signature', file);
                try {
                    const json = await (await fetch('<?= BASE_URL ?>/manageheadaccount', { method: 'POST', body: fd })).json();
                    if (json?.success) {
                        showToast(json.message || 'Signature uploaded');
                        const row = uploadSigBtn.closest('li[data-id]');
                        if (row) {
                            const el = row.querySelector('.w-12.h-12');
                            if (el) el.outerHTML = `<img src="view_signature.php?id=${currentSignatureId}&t=${Date.now()}" alt="Signature" class="w-12 h-12 object-contain border border-gray-200 rounded-lg bg-white"/>`;
                        }
                        cleanup();
                    } else {
                        showToast(json?.message || 'Upload failed', 'error');
                    }
                } catch { showToast('Network error', 'error'); }
                finally { signatureSave.disabled = false; signatureSave.textContent = 'Upload'; }
            };
            const cancelH = () => cleanup();
            signatureSave.addEventListener('click', saveH);
            signatureCancel.addEventListener('click', cancelH);
            return;
        }

        // Edit Subrole
        if (editSubroleBtn) {
            const id = editSubroleBtn.dataset.id;
            const currentSubrole = editSubroleBtn.dataset.subrole || '';
            const row  = editSubroleBtn.closest('li[data-id]');
            const dept = row?.dataset.dept || '';
            subroleSelect.innerHTML = '<option value="">— No Role —</option>';
            (departmentSubroles[dept] || []).forEach(role => {
                const opt = document.createElement('option');
                opt.value = role; opt.textContent = role;
                if (role === currentSubrole) opt.selected = true;
                subroleSelect.appendChild(opt);
            });
            const isCustom = currentSubrole && !(departmentSubroles[dept] || []).includes(currentSubrole);
            subroleCustomToggle.checked = isCustom;
            subroleCustomContainer.classList.toggle('hidden', !isCustom);
            subroleCustomInput.value = isCustom ? currentSubrole : '';
            subroleSelect.disabled = isCustom;
            subroleDeptName.textContent = `Editing role for ${dept.replace(/\b\w/g, c => c.toUpperCase())} member`;
            subroleModal.classList.remove('hidden');

            const toggleH = () => {
                const c = subroleCustomToggle.checked;
                subroleCustomContainer.classList.toggle('hidden', !c);
                subroleSelect.disabled = c;
                if (!c) subroleCustomInput.value = '';
            };
            subroleCustomToggle.addEventListener('change', toggleH);

            const cleanup = () => {
                subroleModal.classList.add('hidden');
                subroleCustomToggle.removeEventListener('change', toggleH);
                subroleSave.removeEventListener('click', saveH);
                subroleCancel.removeEventListener('click', cancelH);
            };
            const saveH = async () => {
                const newRole = subroleCustomToggle.checked ? subroleCustomInput.value.trim() : subroleSelect.value;
                subroleSave.disabled = true; subroleSave.textContent = 'Saving…';
                try {
                    const json = await sendAction(id, 'update_subrole', { subrole: newRole });
                    if (json?.success) {
                        showToast(json.message || 'Role updated');
                        editSubroleBtn.dataset.subrole = newRole;
                        const existing = row?.querySelector('.text-blue-500');
                        if (newRole) {
                            if (existing) { existing.innerHTML = `<i class="fas fa-tag mr-1"></i>${newRole}`; }
                            else {
                                const p = document.createElement('p');
                                p.className = 'text-xs text-blue-500 mt-1';
                                p.innerHTML = `<i class="fas fa-tag mr-1"></i>${newRole}`;
                                row.querySelector('.text-xs.text-gray-400')?.after(p);
                            }
                        } else { existing?.remove(); }
                        cleanup();
                    } else { showToast(json?.message || 'Failed', 'error'); }
                } catch { showToast('Network error', 'error'); }
                finally { subroleSave.disabled = false; subroleSave.textContent = 'Save'; }
            };
            const cancelH = () => cleanup();
            subroleSave.addEventListener('click', saveH);
            subroleCancel.addEventListener('click', cancelH);
            return;
        }

        // Edit Commission
        if (editCommBtn) {
            const id = editCommBtn.dataset.id;
            commissionInput.value = editCommBtn.dataset.commission || '0.00';
            commissionModal.classList.remove('hidden');
            commissionInput.focus();

            const cleanup = () => {
                commissionModal.classList.add('hidden');
                commissionSave.removeEventListener('click', saveH);
                commissionCancel.removeEventListener('click', cancelH);
            };
            const saveH = async () => {
                const val = parseFloat(commissionInput.value) || 0;
                if (val < 0 || val > 100) { showToast('Value must be 0–100', 'error'); return; }
                commissionSave.disabled = true; commissionSave.textContent = 'Saving…';
                try {
                    const json = await sendAction(id, 'update_commission', { commission: val.toFixed(2) });
                    if (json?.success) {
                        showToast(json.message || 'Commission updated');
                        editCommBtn.dataset.commission = val.toFixed(2);
                        const row = editCommBtn.closest('li[data-id]');
                        const existing = row?.querySelector('.text-green-600');
                        if (existing) existing.innerHTML = `<i class="fas fa-percent mr-1"></i>Commission: ${val.toFixed(2)}%`;
                        cleanup();
                    } else { showToast(json?.message || 'Failed', 'error'); }
                } catch { showToast('Network error', 'error'); }
                finally { commissionSave.disabled = false; commissionSave.textContent = 'Save'; }
            };
            const cancelH = () => cleanup();
            commissionSave.addEventListener('click', saveH);
            commissionCancel.addEventListener('click', cancelH);
            return;
        }

        // Set Head
        if (setBtn) {
            const id  = setBtn.dataset.id;
            const row = setBtn.closest('li[data-id]');
            if (!row) return;
            const ok = await confirmDialog('Assign Head', 'Set this member as department head? The current head (if any) will be removed.');
            if (!ok) return;
            setBtn.disabled = true; setBtn.textContent = '…';
            try {
                const json = await sendAction(id, 'set_head');
                if (json?.success) {
                    showToast(json.message || 'Head assigned');
                    const dept = row.dataset.dept;
                    // Clear old heads in same dept
                    $$(`li[data-dept="${dept}"]`).forEach(it => {
                        if (it.dataset.id !== String(id) && it.dataset.isHead === '1') {
                            it.dataset.isHead = '0';
                            it.querySelector('.remove-head')?.outerHTML &&
                                (it.querySelector('.remove-head').outerHTML = `<button type="button" class="set-head text-xs px-2.5 py-1.5 rounded-lg border border-orange-200 text-orange-600 hover:bg-orange-50 transition" data-id="${it.dataset.id}"><i class="fas fa-crown mr-1"></i>Set Head</button>`);
                            it.querySelector('.head-badge')?.remove();
                        }
                    });
                    row.dataset.isHead = '1';
                    row.querySelector('.set-head').outerHTML = `<button type="button" class="remove-head text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition" data-id="${id}"><i class="fas fa-times mr-1"></i>Remove Head</button>`;
                    if (!row.querySelector('.head-badge')) {
                        const wrap = row.querySelector('.flex.flex-wrap.items-center.gap-2');
                        const badge = document.createElement('span');
                        badge.className = 'head-badge inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs px-2 py-0.5 rounded-full font-medium';
                        badge.innerHTML = '<i class="fas fa-crown text-orange-400" style="font-size:10px"></i> Head';
                        wrap?.insertBefore(badge, wrap.firstChild);
                    }
                    refreshHeadsList();
                } else {
                    showToast(json?.message || 'Failed', 'error');
                    setBtn.disabled = false;
                    setBtn.innerHTML = '<i class="fas fa-crown mr-1"></i>Set Head';
                }
            } catch { showToast('Network error', 'error'); setBtn.disabled = false; setBtn.innerHTML = '<i class="fas fa-crown mr-1"></i>Set Head'; }
            return;
        }

        // Remove Head
        if (removeBtn) {
            const id  = removeBtn.dataset.id;
            const row = removeBtn.closest('li[data-id]');
            if (!row) return;
            const ok = await confirmDialog('Remove Head', 'Remove head role from this member?');
            if (!ok) return;
            removeBtn.disabled = true; removeBtn.textContent = '…';
            try {
                const json = await sendAction(id, 'remove_head');
                if (json?.success) { location.reload(); }
                else {
                    showToast(json?.message || 'Failed', 'error');
                    removeBtn.disabled = false;
                    removeBtn.innerHTML = '<i class="fas fa-times mr-1"></i>Remove Head';
                }
            } catch { showToast('Network error', 'error'); removeBtn.disabled = false; removeBtn.innerHTML = '<i class="fas fa-times mr-1"></i>Remove Head'; }
            return;
        }
    });

    // ── Search ──
    searchInput.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        $$('li[data-id]').forEach(li => {
            const name  = li.querySelector('.font-medium')?.textContent.toLowerCase() || '';
            const email = li.querySelector('.text-gray-400')?.textContent.toLowerCase() || '';
            li.style.display = (!q || name.includes(q) || email.includes(q)) ? '' : 'none';
        });
        refreshHeadsList();
    });

    // ── Init ──
    const initDept = new URLSearchParams(window.location.search).get('dept') || '';
    applyFilter(initDept);
});
</script>
</body>
</html>